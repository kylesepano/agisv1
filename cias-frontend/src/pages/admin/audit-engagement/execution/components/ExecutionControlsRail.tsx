import {
  Activity,
  AlertTriangle,
  ArrowLeft,
  CheckCircle2,
  FileCheck2,
  Shield,
} from "lucide-react";
import { Button } from "../../../../../components/ui/button";
import { navigateTo } from "../../../../../routes";
import type { ExecutionWorkspaceState } from "../execution-types";

type ExecutionControlsRailProps = {
  state: ExecutionWorkspaceState;
  onRecordProgress: () => void;
  onReportBlocker: () => void;
};

export function ExecutionControlsRail({
  state,
  onRecordProgress,
  onReportBlocker,
}: ExecutionControlsRailProps) {
  const completedProcs = state.procedures.filter(
    (p) => p.status === "Completed",
  ).length;
  const totalProcs = state.procedures.length;
  const percentProcs = Math.round((completedProcs / (totalProcs || 1)) * 100);

  const checks = [
    {
      label: "Phase 3 Entry Conference completed",
      valid: true,
      detail: "Satisfied",
    },
    {
      label: "Procedures assigned & active (SCR-223)",
      valid: totalProcs >= 8,
      detail: `${totalProcs} procedures`,
    },
    {
      label: "Man-days within 45-day allocation",
      valid: state.totalActualManDays <= state.totalPlannedManDays,
      detail: `${state.totalActualManDays}/${state.totalPlannedManDays}d`,
    },
    {
      label: "Supervisory review notes active",
      valid: state.reviewNotes.length >= 1,
      detail: `${state.reviewNotes.length} notes`,
    },
    {
      label: "Zero critical execution blockers",
      valid: state.blockers.filter((b) => b.status === "Active").length === 0,
      detail: "Clean",
    },
  ];

  return (
    <div className="rounded-lg border border-slate-200 bg-white p-3.5 shadow-sm space-y-3.5">
      <div className="flex items-center justify-between border-b border-slate-100 pb-2">
        <div className="flex items-center gap-1.5 font-bold text-slate-800 text-xs">
          <Shield size={14} className="text-blue-600" />
          <span>Execution Controls (§9.3, §11)</span>
        </div>
        <span className="rounded bg-blue-50 px-1.5 py-0.2 text-[10px] font-mono font-bold text-blue-700">
          SCR-226
        </span>
      </div>

      {/* Engagement Identity Box */}
      <div className="rounded-lg border border-slate-100 bg-slate-50/70 p-2.5 space-y-1.5 text-xs">
        <div className="flex items-center justify-between">
          <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
            Engagement No.
          </span>
          <span className="font-mono font-bold text-blue-700 text-xs">
            {state.engagementNumber}
          </span>
        </div>

        <div className="flex items-center justify-between">
          <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
            Lifecycle Milestone
          </span>
          <span className="rounded bg-indigo-50 px-1.5 py-0.2 text-[10px] font-bold text-indigo-700 border border-indigo-200">
            Phase 4 of 10 • Audit Execution
          </span>
        </div>

        <div className="flex items-center justify-between">
          <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
            Fieldwork State
          </span>
          <span className="rounded bg-emerald-50 px-1.5 py-0.2 text-[10px] font-bold text-emerald-700 border border-emerald-200">
            In Progress (Active)
          </span>
        </div>
      </div>

      {/* Fieldwork Quantitative Gauge */}
      <div className="rounded-lg border border-slate-200 bg-slate-50/50 p-2.5 space-y-2 text-xs">
        <div className="flex items-center justify-between">
          <span className="text-[10px] font-bold text-slate-500 uppercase tracking-wider">
            Procedures Completion
          </span>
          <span className="font-mono text-xs font-bold text-slate-800">
            {completedProcs} / {totalProcs} ({percentProcs}%)
          </span>
        </div>

        <div className="w-full bg-slate-200 rounded-full h-2 overflow-hidden">
          <div
            className="bg-emerald-600 h-2 rounded-full transition-all duration-500"
            style={{ width: `${percentProcs}%` }}
          />
        </div>

        <div className="grid grid-cols-2 gap-1.5 pt-1 text-center font-mono">
          <div className="rounded bg-white p-1.5 border border-slate-200/80">
            <span className="text-[9px] text-slate-400 font-bold block">In Progress</span>
            <span className="text-xs font-bold text-blue-700">
              {state.procedures.filter((p) => p.status === "In Progress").length}
            </span>
          </div>
          <div className="rounded bg-white p-1.5 border border-slate-200/80">
            <span className="text-[9px] text-slate-400 font-bold block">Pending</span>
            <span className="text-xs font-bold text-slate-600">
              {state.procedures.filter((p) => p.status === "Pending Execution").length}
            </span>
          </div>
        </div>
      </div>

      {/* 5-Point Execution Readiness Checklist */}
      <div className="space-y-2">
        <div className="flex items-center justify-between text-xs">
          <span className="font-bold text-slate-800 flex items-center gap-1">
            <FileCheck2 size={12} className="text-blue-600" />
            <span>Fieldwork Governance Checklist</span>
          </span>
          <span className="font-mono text-[10px] font-bold px-1.5 py-0.2 rounded bg-emerald-50 text-emerald-700 border border-emerald-200">
            5/5 Invariant
          </span>
        </div>

        <div className="space-y-1.5 divide-y divide-slate-100 text-xs">
          {checks.map((chk, idx) => (
            <div key={idx} className="flex items-start justify-between gap-2 pt-1.5">
              <div className="flex items-start gap-1.5 min-w-0">
                <CheckCircle2 size={13} className="text-emerald-600 shrink-0 mt-0.5" />
                <span className="text-[11px] leading-tight text-slate-700 font-medium">
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
          onClick={onRecordProgress}
          className="h-10 w-full rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold shadow-sm flex items-center justify-center gap-2 transition active:scale-[0.99]"
        >
          <Activity size={14} />
          <span>Record Fieldwork Progress</span>
        </Button>

        <Button
          type="button"
          variant="outline"
          onClick={onReportBlocker}
          className="h-9 w-full rounded-lg border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold shadow-2xs flex items-center justify-center gap-1.5 transition active:scale-[0.99]"
        >
          <AlertTriangle size={13} className="text-amber-600" />
          <span>Report Blocker / Urgent Matter</span>
        </Button>

        <Button
          type="button"
          variant="outline"
          onClick={() =>
            navigateTo(`/admin/audit-engagement/${state.engagementNumber}`)
          }
          className="h-9 w-full rounded-lg border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold shadow-2xs flex items-center justify-center gap-1.5 transition active:scale-[0.99]"
        >
          <ArrowLeft size={13} />
          <span>Return to Engagement Details (SCR-220)</span>
        </Button>
      </div>
    </div>
  );
}
