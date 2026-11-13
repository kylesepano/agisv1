import { Building2, Calendar, FileText, Shield } from "lucide-react";
import type { AepStatus } from "../aep-types";

type AepIdentityContextCardProps = {
  aepId: string;
  engagementNumber: string;
  engagementTitle: string;
  office: string;
  version: string;
  effectiveDate: string;
  status: AepStatus;
};

export function AepIdentityContextCard({
  aepId,
  engagementNumber,
  engagementTitle,
  office,
  version,
  effectiveDate,
  status,
}: AepIdentityContextCardProps) {
  return (
    <div className="rounded-lg border border-slate-200 bg-slate-50/80 p-3 shadow-2xs space-y-2">
      <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200/80 pb-1.5">
        <div className="flex items-center gap-2">
          <Shield className="h-3.5 w-3.5 text-blue-600" />
          <span className="text-[11px] font-bold text-slate-800 uppercase tracking-wider">
            Audit Engagement Plan Baseline (MDS-200 §7, §9.2 · UID-200 §8.3)
          </span>
        </div>
        <div className="flex items-center gap-1.5">
          <span className="rounded bg-indigo-50 px-1.5 py-0.2 text-[10px] font-bold text-indigo-700 border border-indigo-200">
            Phase 2: Audit Planning
          </span>
          <span className="rounded bg-amber-50 px-1.5 py-0.2 text-[10px] font-bold text-amber-700 border border-amber-200">
            {status} ({version})
          </span>
        </div>
      </div>

      <div className="grid grid-cols-1 gap-2 md:grid-cols-3 text-xs">
        {/* AEP Number & Engagement */}
        <div className="space-y-0.5">
          <span className="text-[10px] font-semibold text-slate-400 uppercase tracking-wider block">
            AEP Document Number
          </span>
          <div className="flex items-center gap-1.5 font-bold text-slate-900 font-mono">
            <FileText size={13} className="text-blue-600 shrink-0" />
            <span>{aepId}</span>
          </div>
          <span className="text-[11px] text-slate-500 block truncate">
            {engagementNumber} • {version}
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
            {engagementTitle}
          </span>
        </div>

        {/* Effective Date */}
        <div className="space-y-0.5">
          <span className="text-[10px] font-semibold text-slate-400 uppercase tracking-wider block">
            Plan Effective Date
          </span>
          <div className="flex items-center gap-1.5 font-mono text-slate-800 font-bold">
            <Calendar size={13} className="text-indigo-600 shrink-0" />
            <span>{effectiveDate}</span>
          </div>
          <span className="text-[10px] text-slate-400 block">
            Controlled Planning Document
          </span>
        </div>
      </div>
    </div>
  );
}
