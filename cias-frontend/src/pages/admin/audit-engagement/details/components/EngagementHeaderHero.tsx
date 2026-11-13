import {
  ArrowLeft,
  Briefcase,
  Building2,
  FileSpreadsheet,
  Info,
  Sparkles,
} from "lucide-react";
import { navigateTo } from "../../../../../routes";
import { statusDefinitions } from "../engagement-details-data";
import type { FullEngagementDetails } from "../engagement-details-types";

type EngagementHeaderHeroProps = {
  details: FullEngagementDetails;
};

export function EngagementHeaderHero({ details }: EngagementHeaderHeroProps) {
  const getStatusBadgeStyle = (status: string) => {
    switch (status) {
      case "Active":
        return "bg-emerald-50 text-emerald-700 border-emerald-200";
      case "Approved":
        return "bg-blue-50 text-blue-700 border-blue-200";
      case "For Approval":
        return "bg-indigo-50 text-indigo-700 border-indigo-200";
      case "Completed":
        return "bg-purple-50 text-purple-700 border-purple-200";
      case "Closed":
        return "bg-slate-100 text-slate-700 border-slate-300";
      case "On Hold":
        return "bg-amber-50 text-amber-700 border-amber-200";
      case "Cancelled":
        return "bg-rose-50 text-rose-700 border-rose-200";
      default:
        return "bg-amber-50 text-amber-700 border-amber-200";
    }
  };

  const getPriorityStyle = (priority: string) => {
    switch (priority) {
      case "Critical":
        return "bg-rose-50 text-rose-700 border-rose-200";
      case "High":
        return "bg-orange-50 text-orange-700 border-orange-200";
      case "Medium":
        return "bg-amber-50 text-amber-700 border-amber-200";
      default:
        return "bg-emerald-50 text-emerald-700 border-emerald-200";
    }
  };

  return (
    <div className="rounded-lg border border-slate-200 bg-white p-3 shadow-sm space-y-2.5">
      {/* Breadcrumb Row */}
      <div className="flex items-center justify-between text-[11px] text-slate-400">
        <div className="flex items-center gap-1.5 font-medium">
          <button
            type="button"
            onClick={() => navigateTo("/admin/audit-engagements")}
            className="hover:text-blue-600 transition"
          >
            Audit Engagements
          </button>
          <span>/</span>
          <span className="text-slate-700 font-semibold font-mono">
            {details.code}
          </span>
          <span>/</span>
          <span className="text-slate-500">
            Engagement Details (SCR-220 Anchor)
          </span>
        </div>
        <span className="text-[10px] font-mono text-slate-400 hidden sm:inline">
          MDS-200 v0.8 §6.1, §7 • Anchor Screen
        </span>
      </div>

      {/* Main Title & Action Row */}
      <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between pt-0.5">
        <div className="flex items-start gap-2.5">
          <button
            type="button"
            onClick={() => navigateTo("/admin/audit-engagements")}
            className="grid h-8 w-8 shrink-0 place-items-center rounded-md border border-slate-200 bg-slate-50 text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition mt-0.5"
            title="Back to Engagements List"
          >
            <ArrowLeft className="h-4 w-4" />
          </button>

          <div className="space-y-1">
            <div className="flex items-center gap-2 flex-wrap">
              <span className="font-mono text-xs font-bold text-blue-700 bg-blue-50 px-2 py-0.5 rounded border border-blue-200">
                {details.code}
              </span>
              <span className="rounded bg-slate-100 px-1.5 py-0.2 text-[10px] font-bold text-slate-600 border border-slate-200">
                {details.originType}
              </span>
              {details.annualPlanRef && (
                <span className="flex items-center gap-1 font-mono text-[10px] text-emerald-700 bg-emerald-50 px-1.5 py-0.2 rounded border border-emerald-200">
                  <FileSpreadsheet size={10} />
                  <span>{details.annualPlanRef}</span>
                </span>
              )}
            </div>

            <h1 className="text-base sm:text-lg font-bold tracking-tight text-slate-900 leading-snug">
              {details.title}
            </h1>

            <div className="flex items-center gap-2.5 text-xs text-slate-500 flex-wrap">
              <span className="flex items-center gap-1 font-medium text-slate-700">
                <Building2 size={13} className="text-slate-400" />
                {details.office}
              </span>
              <span>•</span>
              <span className="flex items-center gap-1">
                <Briefcase size={13} className="text-slate-400" />
                {details.auditType}
              </span>
              <span>•</span>
              <span className={`rounded px-1.5 py-0.2 text-[10px] font-bold border ${getPriorityStyle(details.priority)}`}>
                {details.priority} Priority
              </span>
              <span className="rounded bg-slate-100 px-1.5 py-0.2 text-[10px] font-medium text-slate-600 border border-slate-200">
                {details.confidentiality}
              </span>
            </div>
          </div>
        </div>

        {/* Dual Independent Phase & Status Badges (UID-200 §5.3) */}
        <div className="flex items-center gap-2 shrink-0 bg-slate-50/80 p-2 rounded-lg border border-slate-200">
          {/* Phase Control */}
          <div className="text-right">
            <span className="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">
              Lifecycle Phase (§10.1)
            </span>
            <div className="mt-0.5 inline-flex items-center gap-1 rounded bg-indigo-50 px-2 py-0.5 text-xs font-bold text-indigo-700 border border-indigo-200">
              <Sparkles size={11} className="text-indigo-500" />
              <span>Phase {details.phaseNumber}: {details.phase.split(". ")[1]}</span>
            </div>
          </div>

          <div className="h-7 w-[1px] bg-slate-200 mx-1" />

          {/* Status Control with Verbatim MDS-200 Tooltip */}
          <div className="text-right">
            <span className="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">
              Status (§10.2)
            </span>
            <div
              className={`mt-0.5 inline-flex items-center gap-1 rounded px-2.5 py-0.5 text-xs font-bold border cursor-help ${getStatusBadgeStyle(
                details.status,
              )}`}
              title={statusDefinitions[details.status]}
            >
              <span>{details.status}</span>
              <Info size={10} className="opacity-60" />
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
