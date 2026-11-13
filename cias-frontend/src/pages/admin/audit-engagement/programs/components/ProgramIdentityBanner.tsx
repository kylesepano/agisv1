import { Building2, FileText, Shield } from "lucide-react";

type ProgramIdentityBannerProps = {
  engagementNumber: string;
  engagementTitle: string;
  office: string;
  totalPrograms: number;
  totalProcedures: number;
  totalManDays: number;
};

export function ProgramIdentityBanner({
  engagementNumber,
  engagementTitle,
  office,
  totalPrograms,
  totalProcedures,
  totalManDays,
}: ProgramIdentityBannerProps) {
  return (
    <div className="rounded-lg border border-slate-200 bg-slate-50/80 p-3 shadow-2xs space-y-2">
      <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200/80 pb-1.5">
        <div className="flex items-center gap-2">
          <Shield className="h-3.5 w-3.5 text-blue-600" />
          <span className="text-[11px] font-bold text-slate-800 uppercase tracking-wider">
            Audit Programs Package Baseline (MDS-200 §7, §9.2 · UID-200 §8.3)
          </span>
        </div>
        <div className="flex items-center gap-1.5">
          <span className="rounded bg-indigo-50 px-1.5 py-0.2 text-[10px] font-bold text-indigo-700 border border-indigo-200">
            Phase 2: Audit Planning
          </span>
          <span className="rounded bg-blue-50 px-1.5 py-0.2 text-[10px] font-bold text-blue-700 border border-blue-200">
            MDS-200 §19 OD-008 Area Mapping
          </span>
        </div>
      </div>

      <div className="grid grid-cols-1 gap-2 sm:grid-cols-3 text-xs">
        {/* Engagement Info */}
        <div className="space-y-0.5">
          <span className="text-[10px] font-semibold text-slate-400 uppercase tracking-wider block">
            Engagement Identity
          </span>
          <div className="flex items-center gap-1.5 font-bold text-slate-900 font-mono">
            <FileText size={13} className="text-blue-600 shrink-0" />
            <span>{engagementNumber}</span>
          </div>
          <span className="text-[11px] text-slate-500 block truncate">
            {engagementTitle}
          </span>
        </div>

        {/* Target Auditee */}
        <div className="space-y-0.5">
          <span className="text-[10px] font-semibold text-slate-400 uppercase tracking-wider block">
            Target Auditee Office
          </span>
          <div className="flex items-center gap-1.5 font-bold text-slate-900">
            <Building2 size={13} className="text-slate-400 shrink-0" />
            <span className="truncate">{office}</span>
          </div>
          <span className="text-[10px] text-slate-400 block truncate">
            {office} • Auditee Operations & Facilities
          </span>
        </div>

        {/* Quantitative Scope Summary */}
        <div className="space-y-0.5">
          <span className="text-[10px] font-semibold text-slate-400 uppercase tracking-wider block">
            Scope & Resource Reconciled
          </span>
          <div className="flex items-center gap-2 font-mono text-slate-800 font-bold">
            <span className="text-blue-700">{totalPrograms} Programs</span>
            <span>•</span>
            <span className="text-emerald-700">{totalProcedures} Procedures</span>
            <span>•</span>
            <span className="text-indigo-700">{totalManDays} Man-Days</span>
          </div>
          <span className="text-[10px] text-slate-400 block">
            Reconciled 100% against AEP & ERM
          </span>
        </div>
      </div>
    </div>
  );
}
