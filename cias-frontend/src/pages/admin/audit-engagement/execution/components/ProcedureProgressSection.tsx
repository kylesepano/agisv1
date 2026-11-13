import {
  CheckCircle2,
  Clock,
  ExternalLink,
  FileSpreadsheet,
} from "lucide-react";
import { navigateTo } from "../../../../../routes";
import type { ProcedureProgressItem } from "../execution-types";

type ProcedureProgressSectionProps = {
  procedures: ProcedureProgressItem[];
  engagementNumber: string;
};

export function ProcedureProgressSection({
  procedures,
  engagementNumber,
}: ProcedureProgressSectionProps) {
  const completedCount = procedures.filter(
    (p) => p.status === "Completed",
  ).length;
  const inProgressCount = procedures.filter(
    (p) => p.status === "In Progress",
  ).length;
  const pendingCount = procedures.filter(
    (p) => p.status === "Pending Execution",
  ).length;

  return (
    <div className="rounded-lg border border-slate-200 bg-white p-3.5 shadow-sm space-y-3">
      <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-2">
        <div className="flex items-center gap-2">
          <FileSpreadsheet className="h-4 w-4 text-blue-600" />
          <h3 className="text-[13px] font-bold text-slate-800">
            Audit Procedures Execution Progress (SCR-223 / SCR-224 Reference)
          </h3>
        </div>
        <div className="flex items-center gap-1.5 text-xs font-mono">
          <span className="rounded bg-emerald-50 px-1.5 py-0.2 text-[10px] font-bold text-emerald-700 border border-emerald-200">
            {completedCount} Completed
          </span>
          <span className="rounded bg-blue-50 px-1.5 py-0.2 text-[10px] font-bold text-blue-700 border border-blue-200">
            {inProgressCount} In Progress
          </span>
          <span className="rounded bg-slate-100 px-1.5 py-0.2 text-[10px] font-bold text-slate-600">
            {pendingCount} Pending
          </span>
        </div>
      </div>

      <div className="overflow-x-auto">
        <table className="w-full text-left text-xs">
          <thead>
            <tr className="border-b border-slate-200 bg-slate-50/70 text-[10px] font-bold uppercase tracking-wider text-slate-500">
              <th className="py-2 px-2.5">Procedure ID</th>
              <th className="py-2 px-2.5">Procedure Title & Scope</th>
              <th className="py-2 px-2.5">Assigned Auditor</th>
              <th className="py-2 px-2.5 text-center">Man-Days (Act/Plan)</th>
              <th className="py-2 px-2.5">WP Reference</th>
              <th className="py-2 px-2.5 text-center">Status</th>
              <th className="py-2 px-2.5 text-right">Action</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {procedures.map((proc) => {
              const isDone = proc.status === "Completed";
              const isDoing = proc.status === "In Progress";

              return (
                <tr
                  key={proc.id}
                  className="hover:bg-slate-50/80 transition group"
                >
                  <td className="py-2.5 px-2.5 font-mono text-[11px] font-bold text-blue-700 whitespace-nowrap">
                    {proc.id}
                  </td>
                  <td className="py-2.5 px-2.5 max-w-[280px]">
                    <span className="font-semibold text-slate-900 block leading-snug">
                      {proc.title}
                    </span>
                    <span className="text-[10px] text-slate-400 block truncate">
                      {proc.purpose}
                    </span>
                  </td>
                  <td className="py-2.5 px-2.5 text-slate-700 whitespace-nowrap">
                    <span className="font-medium text-[11px]">
                      {proc.assignedAuditor}
                    </span>
                  </td>
                  <td className="py-2.5 px-2.5 text-center font-mono text-[11px] whitespace-nowrap">
                    <span className="font-bold text-slate-800">
                      {proc.actualManDays}
                    </span>
                    <span className="text-slate-400"> / {proc.plannedManDays}d</span>
                  </td>
                  <td className="py-2.5 px-2.5 font-mono text-[10px] font-bold text-purple-700 whitespace-nowrap">
                    {proc.workingPaperRef}
                  </td>
                  <td className="py-2.5 px-2.5 text-center whitespace-nowrap">
                    <span
                      className={`inline-flex items-center gap-1 rounded px-2 py-0.5 text-[10px] font-bold border ${
                        isDone
                          ? "bg-emerald-50 text-emerald-700 border-emerald-200"
                          : isDoing
                          ? "bg-blue-50 text-blue-700 border-blue-200"
                          : "bg-slate-100 text-slate-600 border-slate-200"
                      }`}
                    >
                      {isDone ? (
                        <CheckCircle2 size={10} className="shrink-0" />
                      ) : isDoing ? (
                        <Clock size={10} className="shrink-0" />
                      ) : null}
                      <span>{proc.status}</span>
                    </span>
                  </td>
                  <td className="py-2.5 px-2.5 text-right whitespace-nowrap">
                    <button
                      type="button"
                      onClick={() =>
                        navigateTo(
                          `/admin/audit-engagement/${engagementNumber}/planning/procedures/${proc.id}`,
                        )
                      }
                      className="inline-flex items-center gap-1 text-[11px] font-semibold text-blue-600 hover:text-blue-800 hover:underline cursor-pointer"
                    >
                      <span>SCR-224 Detail</span>
                      <ExternalLink size={10} />
                    </button>
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>
    </div>
  );
}
