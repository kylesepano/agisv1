import { Building2, FileText, Shield, Users } from "lucide-react";
import type { ConferenceStatus, ConferenceType } from "../conference-types";

type ConferenceIdentityCardProps = {
  conferenceId: string;
  engagementNumber: string;
  engagementTitle: string;
  office: string;
  type: ConferenceType;
  status: ConferenceStatus;
  isWaiver: boolean;
};

export function ConferenceIdentityCard({
  conferenceId,
  engagementNumber,
  engagementTitle,
  office,
  type,
  status,
  isWaiver,
}: ConferenceIdentityCardProps) {
  return (
    <div className="rounded-lg border border-slate-200 bg-slate-50/80 p-3 shadow-2xs space-y-2">
      <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200/80 pb-1.5">
        <div className="flex items-center gap-2">
          <Shield className="h-3.5 w-3.5 text-blue-600" />
          <span className="text-[11px] font-bold text-slate-800 uppercase tracking-wider">
            Conference & Fieldwork Authorization Baseline (MDS-200 §7, §11 · UID-200 §8.3)
          </span>
        </div>
        <div className="flex items-center gap-1.5">
          <span className="rounded bg-indigo-50 px-1.5 py-0.2 text-[10px] font-bold text-indigo-700 border border-indigo-200">
            Phase 3: Entry Conference & Commencement
          </span>
          <span
            className={`rounded px-1.5 py-0.2 text-[10px] font-bold border ${
              isWaiver
                ? "bg-amber-50 text-amber-700 border-amber-200"
                : "bg-emerald-50 text-emerald-700 border-emerald-200"
            }`}
          >
            {isWaiver ? "Authorized Waiver" : `${type} (${status})`}
          </span>
        </div>
      </div>

      <div className="grid grid-cols-1 gap-2 sm:grid-cols-3 text-xs">
        {/* Conference Document Info */}
        <div className="space-y-0.5">
          <span className="text-[10px] font-semibold text-slate-400 uppercase tracking-wider block">
            Conference Identifier
          </span>
          <div className="flex items-center gap-1.5 font-bold text-slate-900 font-mono">
            <FileText size={13} className="text-blue-600 shrink-0" />
            <span>{conferenceId}</span>
          </div>
          <span className="text-[11px] text-slate-500 block truncate">
            {engagementNumber} • {type}
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

        {/* Milestone Gate */}
        <div className="space-y-0.5">
          <span className="text-[10px] font-semibold text-slate-400 uppercase tracking-wider block">
            Fieldwork Commencement Invariant (§11 Rule 11)
          </span>
          <div className="flex items-center gap-1.5 font-mono text-slate-800 font-bold">
            <Users size={13} className="text-blue-600 shrink-0" />
            <span>Entry Protocol Mandate</span>
          </div>
          <span className="text-[10px] text-slate-400 block">
            Satisfies prerequisite for Audit Execution (Phase 4)
          </span>
        </div>
      </div>
    </div>
  );
}
