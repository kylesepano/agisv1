import {
  Binary,
  Calendar,
  FolderLock,
  HardDrive,
  UserCheck,
} from "lucide-react";
import type { AuditEvidenceItem } from "../evidence-types";

type EvidenceIdentityBannerProps = {
  evidence: AuditEvidenceItem;
  engagementTitle?: string;
  office?: string;
};

export function EvidenceIdentityBanner({
  evidence,
}: EvidenceIdentityBannerProps) {
  const getStatusBadgeClass = (status: AuditEvidenceItem["status"]) => {
    switch (status) {
      case "Accepted":
        return "bg-emerald-50 text-emerald-700 border-emerald-300";
      case "Accepted with Limitation":
        return "bg-amber-50 text-amber-800 border-amber-300";
      case "For Assessment":
        return "bg-blue-50 text-blue-700 border-blue-300";
      case "Registered":
        return "bg-slate-100 text-slate-700 border-slate-300";
      case "Additional Evidence Required":
        return "bg-purple-50 text-purple-700 border-purple-300";
      case "Rejected":
        return "bg-rose-50 text-rose-700 border-rose-300";
      case "Superseded":
        return "bg-orange-50 text-orange-700 border-orange-300";
      case "Duplicate":
        return "bg-zinc-100 text-zinc-600 border-zinc-300";
      default:
        return "bg-slate-100 text-slate-700 border-slate-200";
    }
  };

  return (
    <div className="rounded-lg border border-slate-200 bg-white p-3.5 shadow-sm space-y-2.5">
      {/* Top row with ID, Title, and Status badge */}
      <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-2">
        <div className="flex items-center gap-2">
          <div className="grid h-7 w-7 place-items-center rounded bg-emerald-50 text-emerald-700 border border-emerald-200">
            <FolderLock className="h-4 w-4" />
          </div>
          <div>
            <div className="flex items-center gap-2">
              <span className="font-mono text-xs font-bold text-emerald-800">
                {evidence.id}
              </span>
              <span className="text-xs font-bold text-slate-900 truncate max-w-md">
                {evidence.title}
              </span>
              <span className="rounded bg-slate-100 px-1.5 py-0.2 font-mono text-[10px] text-slate-600">
                {evidence.version}
              </span>
            </div>
          </div>
        </div>

        <div className="flex items-center gap-2">
          <span className="rounded bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-800 border border-emerald-200">
            {evidence.evidenceType}
          </span>
          <span
            className={`rounded px-2 py-0.5 text-[10px] font-bold border ${getStatusBadgeClass(
              evidence.status,
            )}`}
          >
            MDS §10.6: {evidence.status}
          </span>
        </div>
      </div>

      {/* Metadata Grid */}
      <div className="grid grid-cols-1 gap-2 text-xs sm:grid-cols-2 lg:grid-cols-4">
        <div className="flex items-start gap-1.5">
          <HardDrive size={13} className="text-slate-400 mt-0.5 shrink-0" />
          <div className="min-w-0">
            <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">
              File Reference / URI
            </span>
            <span className="font-mono text-[11px] text-slate-700 truncate block" title={evidence.fileReference}>
              {evidence.fileReference}
            </span>
          </div>
        </div>

        <div className="flex items-start gap-1.5">
          <UserCheck size={13} className="text-slate-400 mt-0.5 shrink-0" />
          <div className="min-w-0">
            <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">
              Active Custodian
            </span>
            <span className="font-semibold text-slate-800 truncate block text-[11px]">
              {evidence.custodian}
            </span>
          </div>
        </div>

        <div className="flex items-start gap-1.5">
          <Calendar size={13} className="text-slate-400 mt-0.5 shrink-0" />
          <div className="min-w-0">
            <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">
              Date Obtained / Origin
            </span>
            <span className="font-medium text-slate-800 block text-[11px]">
              {evidence.dateReceived} ({evidence.sourceOrigin})
            </span>
          </div>
        </div>

        <div className="flex items-start gap-1.5">
          <Binary size={13} className="text-emerald-600 mt-0.5 shrink-0" />
          <div className="min-w-0">
            <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">
              SHA-256 Hash Seal
            </span>
            <span className="font-mono text-[10px] text-emerald-800 truncate block" title={evidence.hashChecksum}>
              {evidence.hashChecksum.substring(0, 16)}...
            </span>
          </div>
        </div>
      </div>
    </div>
  );
}
