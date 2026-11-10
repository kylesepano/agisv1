import {
  Building2,
  Calendar,
  Shield,
} from "lucide-react";

type ExecutionIdentityBannerProps = {
  engagementNumber: string;
  engagementTitle: string;
  office: string;
  fieldworkStartDate: string;
  fieldworkEndDate: string;
  totalPlannedManDays: number;
  totalActualManDays: number;
};

export function ExecutionIdentityBanner({
  engagementNumber,
  engagementTitle,
  office,
  fieldworkStartDate,
  fieldworkEndDate,
  totalPlannedManDays,
  totalActualManDays,
}: ExecutionIdentityBannerProps) {
  const percentManDays = Math.round(
    (totalActualManDays / (totalPlannedManDays || 1)) * 100,
  );

  return (
    <div className="rounded-lg border border-slate-200 bg-slate-50/80 p-3 shadow-2xs space-y-2">
      <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200/80 pb-1.5">
        <div className="flex items-center gap-2">
          <Shield className="h-3.5 w-3.5 text-blue-600" />
          <span className="text-[11px] font-bold text-slate-800 uppercase tracking-wider">
            SCR-226 — Audit Execution Workspace Hub (UID-200 §5.2, §8.3 · MDS-200 §10.1)
          </span>
        </div>
        <div className="flex items-center gap-2">
          <span className="rounded bg-indigo-50 px-2 py-0.5 text-[10px] font-bold text-indigo-700 border border-indigo-200">
            Phase 4 of 10 • Audit Execution
          </span>
          <span className="rounded bg-emerald-50 px-1.5 py-0.2 text-[10px] font-bold text-emerald-700 border border-emerald-200">
            Fieldwork Active
          </span>
        </div>
      </div>

      <div className="grid grid-cols-1 gap-2.5 sm:grid-cols-3 text-xs">
        {/* Engagement & Office */}
        <div>
          <span className="text-[10px] font-semibold text-slate-400 uppercase tracking-wider block">
            Target Auditee & Scope
          </span>
          <div className="flex items-center gap-1.5 font-bold text-slate-900">
            <Building2 size={13} className="text-slate-400 shrink-0" />
            <span className="truncate">{office}</span>
          </div>
          <span className="text-[11px] text-slate-500 block truncate">
            {engagementNumber} • {engagementTitle}
          </span>
        </div>

        {/* Fieldwork Window */}
        <div>
          <span className="text-[10px] font-semibold text-slate-400 uppercase tracking-wider block">
            Fieldwork Execution Window
          </span>
          <div className="flex items-center gap-1 font-mono font-bold text-slate-800">
            <Calendar size={12} className="text-indigo-600 shrink-0" />
            <span>{fieldworkStartDate} ~ {fieldworkEndDate}</span>
          </div>
          <span className="text-[10px] text-slate-400 block">
            Governed under MDS-200 §9.3 Execution Mandate
          </span>
        </div>

        {/* Man-Days Metric */}
        <div>
          <div className="flex items-center justify-between text-[10px] font-semibold text-slate-400 uppercase tracking-wider">
            <span>Man-Days Burn Rate</span>
            <span className="font-mono text-blue-700 font-bold">
              {totalActualManDays} / {totalPlannedManDays} Days ({percentManDays}%)
            </span>
          </div>
          <div className="w-full bg-slate-200 rounded-full h-2 mt-1 overflow-hidden">
            <div
              className="bg-blue-600 h-2 rounded-full transition-all duration-500"
              style={{ width: `${Math.min(percentManDays, 100)}%` }}
            />
          </div>
          <div className="flex items-center justify-between text-[10px] text-slate-400 pt-0.5 font-mono">
            <span>Entry Conf: Conducted</span>
            <span>Reporting Target: Apr 30</span>
          </div>
        </div>
      </div>
    </div>
  );
}
