import {
  AlertOctagon,
  AlertTriangle,
  Plus,
  ShieldCheck,
} from "lucide-react";
import { Button } from "../../../../../components/ui/button";
import type { ExecutionBlocker } from "../execution-types";

type ExecutionBlockersSectionProps = {
  blockers: ExecutionBlocker[];
  onReportBlocker: () => void;
};

// GAP-002 (docs/mds-200-gaps.md): MDS-200 v0.8 mentions execution blockers and urgent matters in SCR-226
// purpose description but defines no formal Module Rule for blocker escalation workflows.
// Blocker reporting and severity classifications are implemented as an approved House Convention / Implementation Rule.
export function ExecutionBlockersSection({
  blockers,
  onReportBlocker,
}: ExecutionBlockersSectionProps) {
  const activeBlockers = blockers.filter((b) => b.status === "Active");

  if (activeBlockers.length === 0) {
    return (
      <div className="rounded-lg border border-slate-200 bg-white p-3.5 shadow-sm">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div className="flex items-center gap-2.5">
            <div className="grid h-8 w-8 place-items-center rounded-lg bg-emerald-50 text-emerald-600 border border-emerald-200">
              <ShieldCheck size={18} />
            </div>
            <div>
              <div className="flex items-center gap-1.5">
                <h4 className="text-xs font-bold text-slate-800">
                  Execution Blockers & Urgent Matters
                </h4>
                <span className="text-[9px] font-semibold text-slate-600 bg-slate-100 px-1.5 py-0.5 rounded border border-slate-200">
                  Implementation Rule
                </span>
              </div>
              <p className="text-[11px] text-slate-500">
                No active fieldwork blockers reported. Audit procedures and evidence gathering are proceeding on schedule.
              </p>
            </div>
          </div>

          <Button
            type="button"
            variant="outline"
            onClick={onReportBlocker}
            className="h-7 text-xs px-2.5 text-slate-600 hover:text-amber-700 hover:border-amber-300 flex items-center gap-1 cursor-pointer"
          >
            <AlertTriangle size={11} className="text-amber-600" />
            <span>Report Blocker / Urgent Matter</span>
          </Button>
        </div>
      </div>
    );
  }

  return (
    <div className="rounded-lg border border-rose-200 bg-rose-50/70 p-3.5 shadow-sm space-y-3">
      <div className="flex flex-wrap items-center justify-between gap-2 border-b border-rose-200 pb-2">
        <div className="flex items-center gap-2">
          <AlertOctagon className="h-4 w-4 text-rose-600" />
          <h3 className="text-[13px] font-bold text-rose-950">
            Active Execution Blockers ({activeBlockers.length})
          </h3>
          <span className="text-[9px] font-semibold text-rose-800 bg-white/80 px-1.5 py-0.5 rounded border border-rose-200">
            Implementation Rule
          </span>
        </div>
        <Button
          type="button"
          variant="outline"
          onClick={onReportBlocker}
          className="h-7 text-xs px-2.5 text-rose-700 hover:bg-rose-100/60 cursor-pointer"
        >
          <Plus size={12} />
          <span>Report New Blocker</span>
        </Button>
      </div>

      <div className="space-y-2 text-xs">
        {activeBlockers.map((b) => (
          <div
            key={b.id}
            className="rounded-lg border border-rose-300 bg-white p-3 space-y-1.5"
          >
            <div className="flex items-center justify-between">
              <span className="font-bold text-rose-950 text-xs">{b.title}</span>
              <span className="rounded bg-rose-100 px-1.5 py-0.2 text-[10px] font-bold text-rose-800">
                {b.impactLevel} Impact
              </span>
            </div>
            <p className="text-slate-700 text-[11px] leading-relaxed">
              {b.description}
            </p>
            <div className="flex items-center justify-between text-[10px] text-slate-400 font-mono pt-1">
              <span>Reported by {b.reportedBy}</span>
              <span>{b.reportedDate}</span>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
