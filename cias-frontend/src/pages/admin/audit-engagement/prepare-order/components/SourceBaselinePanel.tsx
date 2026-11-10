import {
  AlertTriangle,
  Building2,
  Calendar,
  Camera,
  CheckCircle2,
  FileCheck,
  RotateCcw,
  Shield,
  Users,
} from "lucide-react";
import type { AeoSourceBaselineSnapshot } from "../prepare-order-types";
import { Button } from "../../../../../components/ui/button";

type SourceBaselinePanelProps = {
  baseline: AeoSourceBaselineSnapshot;
  isDrifted: boolean;
  driftSummary?: string;
  onRefreshBaseline: () => void;
  onSimulateDrift?: () => void;
};

export function SourceBaselinePanel({
  baseline,
  isDrifted,
  driftSummary,
  onRefreshBaseline,
  onSimulateDrift,
}: SourceBaselinePanelProps) {
  return (
    <div className="rounded-lg border border-slate-200 bg-white p-3.5 shadow-sm space-y-3">
      {/* Header */}
      <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-2">
        <div className="flex items-center gap-2">
          <Camera className="h-4 w-4 text-blue-600" />
          <h3 className="text-[13px] font-bold text-slate-800">
            Controlled Source Baseline Snapshot
          </h3>
          <span className="rounded bg-blue-50 px-1.5 py-0.2 text-[10px] font-mono font-bold text-blue-700 border border-blue-200">
            {baseline.snapshotVersion}
          </span>
        </div>
        <div className="flex items-center gap-2">
          <span className="text-[10px] text-slate-400 font-mono">
            Snapshotted: {baseline.snapshottedAt}
          </span>
          {onSimulateDrift && (
            <button
              type="button"
              onClick={onSimulateDrift}
              className="text-[9px] text-slate-400 hover:text-slate-600 underline cursor-pointer"
            >
              (test drift)
            </button>
          )}
        </div>
      </div>

      {/* Upstream Drift Detection Banner (MDS-200 §5.1) */}
      {isDrifted ? (
        <div className="rounded-lg border border-amber-300 bg-amber-50/80 p-3 text-xs space-y-2 animate-in fade-in-50">
          <div className="flex items-start justify-between gap-2">
            <div className="flex items-center gap-1.5 text-amber-900 font-bold">
              <AlertTriangle className="h-4 w-4 text-amber-600 shrink-0" />
              <span>Source Changed Since Snapshot (§5.1 Drift Detected)</span>
            </div>
            <Button
              type="button"
              onClick={onRefreshBaseline}
              className="h-7 rounded-md bg-amber-600 hover:bg-amber-700 text-white text-[11px] font-semibold px-2.5 flex items-center gap-1 shadow-2xs cursor-pointer"
            >
              <RotateCcw size={11} />
              <span>Refresh Baseline Snapshot</span>
            </Button>
          </div>
          <p className="text-[11px] text-amber-800/90 leading-snug">
            {driftSummary ||
              "Upstream engagement parameters in SCR-212 (Scope/Office) or SCR-213 (Audit Team) have been modified after this AEO baseline was generated. To prevent issuing stale authority, refresh the baseline to version and re-verify."}
          </p>
        </div>
      ) : (
        <div className="flex items-center justify-between rounded-md border border-emerald-200 bg-emerald-50/60 px-2.5 py-1.5 text-[11px]">
          <div className="flex items-center gap-1.5 text-emerald-800 font-medium">
            <CheckCircle2 size={13} className="text-emerald-600 shrink-0" />
            <span>Baseline in sync with SCR-211 (Authority), SCR-212 (Scope), and SCR-213 (Team)</span>
          </div>
          <span className="text-[10px] text-emerald-700 font-mono font-semibold">Zero Drift</span>
        </div>
      )}

      {/* Snapshot Details 3-Col Grid */}
      <div className="grid grid-cols-1 gap-2.5 md:grid-cols-3">
        {/* SCR-211 Authority Box */}
        <div className="rounded-lg border border-slate-200 bg-slate-50/60 p-2.5 space-y-1 text-xs">
          <div className="flex items-center justify-between text-[10px] font-semibold text-slate-500 uppercase tracking-wider">
            <span className="flex items-center gap-1">
              <Shield size={11} className="text-blue-600" />
              SCR-211 Authority
            </span>
            <span className="text-slate-400 font-mono">{baseline.originType}</span>
          </div>
          <p className="font-semibold text-slate-900 line-clamp-1">
            {baseline.authorityReference}
          </p>
          <p className="text-[11px] text-slate-500 line-clamp-1">
            Directing: <span className="text-slate-700 font-medium">{baseline.directingAuthority}</span>
          </p>
          <div className="pt-1 flex items-center gap-2 text-[10px] text-slate-500 font-mono">
            <Calendar size={10} />
            <span>{baseline.periodStart} ~ {baseline.periodEnd}</span>
          </div>
        </div>

        {/* SCR-212 Office & Scope Box */}
        <div className="rounded-lg border border-slate-200 bg-slate-50/60 p-2.5 space-y-1 text-xs">
          <div className="flex items-center justify-between text-[10px] font-semibold text-slate-500 uppercase tracking-wider">
            <span className="flex items-center gap-1">
              <Building2 size={11} className="text-emerald-600" />
              SCR-212 Scope & Office
            </span>
            <span className="text-emerald-700 font-bold">{baseline.auditType}</span>
          </div>
          <p className="font-semibold text-slate-900 line-clamp-1">
            {baseline.office}
          </p>
          <p className="text-[11px] text-slate-500 line-clamp-1">
            Areas: <span className="text-slate-700 font-medium">{baseline.auditAreas.join(", ")}</span>
          </p>
          <div className="pt-1 flex items-center gap-1 text-[10px] text-slate-500">
            <FileCheck size={10} className="text-blue-600" />
            <span className="line-clamp-1">{baseline.auditFocuses.length} Focus Items tagged</span>
          </div>
        </div>

        {/* SCR-213 Team Box */}
        <div className="rounded-lg border border-slate-200 bg-slate-50/60 p-2.5 space-y-1 text-xs">
          <div className="flex items-center justify-between text-[10px] font-semibold text-slate-500 uppercase tracking-wider">
            <span className="flex items-center gap-1">
              <Users size={11} className="text-indigo-600" />
              SCR-213 Validated Team
            </span>
            <span className="text-indigo-700 font-bold">{baseline.team.length} Members</span>
          </div>
          {baseline.team.map((member) => (
            <div key={member.id} className="flex items-center justify-between text-[11px]">
              <span className="font-medium text-slate-800 truncate">{member.name}</span>
              <span className="rounded bg-white px-1 py-0.2 text-[9px] font-semibold text-slate-600 border border-slate-200 shrink-0">
                {member.role}
              </span>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}
