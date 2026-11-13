import {
  Calendar,
  Clock,
  ExternalLink,
  FileCheck2,
  FileSpreadsheet,
  Link,
  User,
} from "lucide-react";
import { Button } from "../../../../../components/ui/button";
import { navigateTo } from "../../../../../routes";
import type { DetailedProcedureItem } from "../programs-types";

type ProcedureTableRowProps = {
  procedure: DetailedProcedureItem;
  engagementNumber: string;
};

export function ProcedureTableRow({
  procedure,
  engagementNumber,
}: ProcedureTableRowProps) {
  const getMethodBadge = (method: string) => {
    switch (method) {
      case "Inspection":
        return "bg-blue-50 text-blue-700 border-blue-200";
      case "Sampling & Vouching":
        return "bg-emerald-50 text-emerald-700 border-emerald-200";
      case "Recalculation":
        return "bg-purple-50 text-purple-700 border-purple-200";
      case "Analytical Review":
        return "bg-amber-50 text-amber-700 border-amber-200";
      case "Observation":
        return "bg-indigo-50 text-indigo-700 border-indigo-200";
      default:
        return "bg-slate-50 text-slate-700 border-slate-200";
    }
  };

  return (
    <div className="rounded-lg border border-slate-200 bg-slate-50/50 p-3 space-y-2.5 transition hover:bg-slate-50 text-xs">
      {/* Header: ID, PFD Stage, Risk Link, and SCR-224 Button */}
      <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200/80 pb-2">
        <div className="flex flex-wrap items-center gap-2">
          <span className="font-mono text-[11px] font-bold text-blue-700 bg-white px-2 py-0.5 rounded border border-blue-200">
            {procedure.id}
          </span>
          <span className="rounded bg-amber-50 px-1.5 py-0.2 text-[10px] font-mono font-bold text-amber-800 border border-amber-200 flex items-center gap-1">
            <Link size={10} />
            <span>{procedure.riskMatrixItemId}</span>
          </span>
          <span className="text-[11px] font-semibold text-slate-700">
            {procedure.pfdStageRef}
          </span>
        </div>

        <div className="flex items-center gap-2">
          <span
            className={`rounded px-1.5 py-0.2 text-[9px] font-bold border ${getMethodBadge(
              procedure.auditMethod,
            )}`}
          >
            {procedure.auditMethod}
          </span>
          <Button
            type="button"
            variant="outline"
            onClick={() =>
              navigateTo(
                `/admin/audit-engagement/${engagementNumber}/planning/procedures/${procedure.id}`,
              )
            }
            className="h-6 text-[10px] px-2 flex items-center gap-1 text-blue-600 hover:text-blue-700 border-blue-200 hover:bg-blue-50 cursor-pointer"
          >
            <span>SCR-224 Detail</span>
            <ExternalLink size={10} />
          </Button>
        </div>
      </div>

      {/* Procedure Title & Purpose */}
      <div className="space-y-1">
        <h4 className="font-bold text-slate-900 text-xs leading-snug">
          {procedure.procedureTitle}
        </h4>
        <p className="text-[11px] text-slate-600 leading-relaxed">
          <strong className="text-slate-700">Purpose (MDS-200 §7):</strong> {procedure.purpose}
        </p>
      </div>

      {/* Expected Evidence Box */}
      <div className="rounded-md bg-white p-2 border border-slate-200/80 text-[11px] text-slate-700 flex items-start gap-2">
        <FileCheck2 size={13} className="text-emerald-600 shrink-0 mt-0.5" />
        <div className="space-y-0.5">
          <span className="font-bold text-[10px] text-slate-500 uppercase tracking-wider block">
            Expected Working Paper Evidence & Documentation Trail
          </span>
          <p className="leading-snug">{procedure.expectedEvidence}</p>
        </div>
      </div>

      {/* Metadata Strip: Responsible, Man-Days, WP Ref, Due Date */}
      <div className="grid grid-cols-2 gap-2 sm:grid-cols-4 text-[10px] pt-1 border-t border-slate-200/60 font-mono text-slate-600">
        <div className="flex items-center gap-1 truncate">
          <User size={11} className="text-blue-600 shrink-0" />
          <span className="truncate">{procedure.personResponsible}</span>
        </div>

        <div className="flex items-center gap-1">
          <Clock size={11} className="text-indigo-600 shrink-0" />
          <span className="font-bold text-slate-800">
            {procedure.plannedManDays} Man-Days
          </span>
        </div>

        <div className="flex items-center gap-1 truncate">
          <FileSpreadsheet size={11} className="text-purple-600 shrink-0" />
          <span className="text-purple-800 font-bold truncate">
            {procedure.workingPaperRef}
          </span>
        </div>

        <div className="flex items-center gap-1">
          <Calendar size={11} className="text-slate-400 shrink-0" />
          <span>Due {procedure.dueDate}</span>
        </div>
      </div>
    </div>
  );
}
