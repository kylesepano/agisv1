import { Building2, FileSpreadsheet, Sparkles, Tag } from "lucide-react";
import type { WorkingPaper } from "../working-papers-types";

type WorkingPaperIdentityBannerProps = {
  workingPaper: WorkingPaper;
  engagementTitle: string;
  office: string;
};

export function WorkingPaperIdentityBanner({
  workingPaper,
  engagementTitle,
  office,
}: WorkingPaperIdentityBannerProps) {
  const getStatusBadgeClass = (status: WorkingPaper["reviewStatus"]) => {
    switch (status) {
      case "Cleared":
        return "bg-emerald-50 text-emerald-700 border-emerald-200";
      case "Reviewed":
        return "bg-blue-50 text-blue-700 border-blue-200";
      case "Submitted":
        return "bg-amber-50 text-amber-800 border-amber-200";
      case "Draft":
      default:
        return "bg-slate-100 text-slate-700 border-slate-200";
    }
  };

  return (
    <div className="rounded-lg border border-slate-200 bg-white p-3.5 shadow-sm space-y-2.5">
      <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-2">
        <div className="flex items-center gap-2">
          <div className="grid h-7 w-7 place-items-center rounded bg-purple-50 text-purple-700 border border-purple-200">
            <FileSpreadsheet className="h-4 w-4" />
          </div>
          <div>
            <div className="flex items-center gap-2">
              <span className="font-mono text-xs font-bold text-purple-700">
                {workingPaper.id}
              </span>
              <span className="text-[11px] text-slate-400 font-mono">
                ({workingPaper.referenceNumber})
              </span>
              <span className="rounded bg-slate-100 px-1.5 py-0.2 font-mono text-[10px] text-slate-600">
                {workingPaper.version}
              </span>
            </div>
          </div>
        </div>

        <div className="flex items-center gap-2">
          <span className="rounded bg-purple-50 px-2 py-0.5 text-[10px] font-bold text-purple-700 border border-purple-200">
            Type: {workingPaper.type}
          </span>
          <span
            className={`rounded px-2 py-0.5 text-[10px] font-bold border ${getStatusBadgeClass(
              workingPaper.reviewStatus,
            )}`}
          >
            Review Status: {workingPaper.reviewStatus}
          </span>
        </div>
      </div>

      <div className="grid grid-cols-1 gap-2 text-xs sm:grid-cols-2 lg:grid-cols-3">
        <div className="flex items-start gap-1.5">
          <Tag size={13} className="text-slate-400 mt-0.5 shrink-0" />
          <div className="min-w-0">
            <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">
              Engagement Context
            </span>
            <span className="font-semibold text-slate-800 truncate block">
              {workingPaper.engagementNumber} — {engagementTitle}
            </span>
          </div>
        </div>

        <div className="flex items-start gap-1.5">
          <Building2 size={13} className="text-slate-400 mt-0.5 shrink-0" />
          <div className="min-w-0">
            <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">
              Target Auditee Office
            </span>
            <span className="font-semibold text-slate-800 truncate block">
              {office}
            </span>
          </div>
        </div>

        <div className="flex items-start gap-1.5 sm:col-span-2 lg:col-span-1">
          <Sparkles size={13} className="text-purple-600 mt-0.5 shrink-0" />
          <div className="min-w-0">
            <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">
              Governance Framework
            </span>
            <span className="font-semibold text-slate-700 block text-[11px]">
              MDS-200 §7 (Working Paper Entity) · Rule 13 Documentation
            </span>
          </div>
        </div>
      </div>
    </div>
  );
}
