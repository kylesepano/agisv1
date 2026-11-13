import {
  Building2,
  FileSpreadsheet,
  FileText,
  Shield,
} from "lucide-react";
import type { FieldworkRecord } from "../fieldwork-types";

type FieldworkIdentityBannerProps = {
  record: FieldworkRecord;
  engagementTitle: string;
  office: string;
};

export function FieldworkIdentityBanner({
  record,
  engagementTitle,
  office,
}: FieldworkIdentityBannerProps) {
  return (
    <div className="rounded-lg border border-slate-200 bg-slate-50/80 p-3 shadow-2xs space-y-2">
      <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200/80 pb-1.5">
        <div className="flex items-center gap-2">
          <Shield className="h-3.5 w-3.5 text-blue-600" />
          <span className="text-[11px] font-bold text-slate-800 uppercase tracking-wider">
            Controlled Fieldwork Record Baseline (MDS-200 §7, §11 · UID-200 §7.3, §8.3)
          </span>
        </div>
        <div className="flex items-center gap-1.5">
          <span className="rounded bg-indigo-50 px-2 py-0.5 text-[10px] font-bold text-indigo-700 border border-indigo-200">
            Phase 4 • Fieldwork Source Artifact
          </span>
          <span className="rounded bg-amber-50 px-1.5 py-0.2 text-[10px] font-bold text-amber-800 border border-amber-200">
            {record.status} ({record.version}) · OD-MDS Provisional
          </span>
        </div>
      </div>

      <div className="grid grid-cols-1 gap-2.5 sm:grid-cols-3 text-xs">
        {/* Record Identifier */}
        <div>
          <span className="text-[10px] font-semibold text-slate-400 uppercase tracking-wider block">
            Record Identifier & Type
          </span>
          <div className="flex items-center gap-1.5 font-bold text-slate-900 font-mono">
            <FileText size={13} className="text-blue-600 shrink-0" />
            <span>{record.id}</span>
          </div>
          <span className="text-[11px] text-slate-600 font-medium block truncate">
            {record.type} • {record.date}
          </span>
        </div>

        {/* Target Office & Scope */}
        <div>
          <span className="text-[10px] font-semibold text-slate-400 uppercase tracking-wider block">
            Target Auditee Office
          </span>
          <div className="flex items-center gap-1.5 font-bold text-slate-900">
            <Building2 size={13} className="text-slate-400 shrink-0" />
            <span className="truncate">{office}</span>
          </div>
          <span className="text-[10px] text-slate-400 block truncate">
            {record.engagementNumber} • {engagementTitle}
          </span>
        </div>

        {/* Linked Audit Procedure (§7.1) */}
        <div>
          <span className="text-[10px] font-semibold text-slate-400 uppercase tracking-wider block">
            Governing Audit Procedure (§7.1 Link)
          </span>
          <div className="flex items-center gap-1.5 font-mono text-blue-700 font-bold">
            <FileSpreadsheet size={13} className="text-blue-600 shrink-0" />
            <span className="truncate">{record.procedureId}</span>
          </div>
          <span className="text-[11px] text-slate-700 font-medium block truncate" title={record.procedureTitle}>
            {record.procedureTitle}
          </span>
        </div>
      </div>
    </div>
  );
}
