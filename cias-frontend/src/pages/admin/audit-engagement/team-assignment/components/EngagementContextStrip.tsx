import { Building2, Calendar, Layers, Shield } from "lucide-react";

type EngagementContextStripProps = {
  engagementNumber: string;
  title: string;
  office: string;
  auditType: string;
  auditAreas: string[];
  periodStart: string;
  periodEnd: string;
};

export function EngagementContextStrip({
  engagementNumber,
  title,
  office,
  auditType,
  auditAreas,
  periodStart,
  periodEnd,
}: EngagementContextStripProps) {
  return (
    <div className="rounded-lg border border-slate-200 bg-slate-50/80 p-3 shadow-2xs space-y-2">
      <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200/80 pb-1.5">
        <div className="flex items-center gap-2">
          <Shield className="h-3.5 w-3.5 text-blue-600" />
          <span className="text-[11px] font-bold text-slate-800 uppercase tracking-wider">
            Upstream Foundation Baseline (SCR-211 & SCR-212 Read-Only Context)
          </span>
        </div>
        <span className="text-[10px] text-slate-400 font-mono">
          Phase 1: Initiation
        </span>
      </div>

      <div className="grid grid-cols-1 gap-2 md:grid-cols-3 text-xs">
        {/* Office & Type */}
        <div className="space-y-0.5">
          <span className="text-[10px] font-semibold text-slate-400 uppercase tracking-wider block">
            Target Office & Audit Type
          </span>
          <div className="flex items-center gap-1.5 font-bold text-slate-900">
            <Building2 size={13} className="text-slate-400 shrink-0" />
            <span className="truncate">{office}</span>
          </div>
          <span className="text-[11px] text-slate-500 block truncate">{auditType} • {engagementNumber}</span>
        </div>

        {/* Audit Areas */}
        <div className="space-y-0.5">
          <span className="text-[10px] font-semibold text-slate-400 uppercase tracking-wider block">
            Established Audit Areas (SCR-212)
          </span>
          <div className="flex items-center gap-1 text-slate-800 font-medium truncate">
            <Layers size={13} className="text-emerald-600 shrink-0" />
            <span className="truncate">{auditAreas.join(" • ")}</span>
          </div>
          <span className="text-[10px] text-slate-400 block truncate">{title}</span>
        </div>

        {/* Period Covered */}
        <div className="space-y-0.5">
          <span className="text-[10px] font-semibold text-slate-400 uppercase tracking-wider block">
            Period Covered (Audit Window)
          </span>
          <div className="flex items-center gap-1.5 font-mono text-slate-800 font-bold">
            <Calendar size={13} className="text-indigo-600 shrink-0" />
            <span>{periodStart} ~ {periodEnd}</span>
          </div>
          <span className="text-[10px] text-slate-400 block">Member assignment dates must fall within this window</span>
        </div>
      </div>
    </div>
  );
}
