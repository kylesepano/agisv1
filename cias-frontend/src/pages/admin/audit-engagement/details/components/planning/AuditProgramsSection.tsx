import {
  ArrowRight,
  BookOpen,
  FileSpreadsheet,
  UserCheck,
} from "lucide-react";
import { Button } from "../../../../../../components/ui/button";
import { navigateTo } from "../../../../../../routes";
import type { AuditProgramItem } from "./planning-types";

type AuditProgramsSectionProps = {
  programs: AuditProgramItem[];
  engagementNumber?: string;
};

export function AuditProgramsSection({
  programs,
  engagementNumber = "AEMS-2026-010",
}: AuditProgramsSectionProps) {
  const totalProcedures = programs.reduce((sum, p) => sum + p.proceduresCount, 0);

  return (
    <div className="rounded-lg border border-slate-200 bg-white p-3.5 shadow-sm space-y-3">
      <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-2">
        <div className="flex items-center gap-2">
          <BookOpen className="h-4 w-4 text-blue-600" />
          <h3 className="text-[13px] font-bold text-slate-800">
            Audit Programs & Detailed Testing Procedures (SCR-223 / SCR-224)
          </h3>
          <span className="rounded bg-blue-50 px-1.5 py-0.2 text-[10px] font-bold text-blue-700 border border-blue-200">
            {programs.length} Programs • {totalProcedures} Procedures
          </span>
        </div>

        <div className="flex items-center gap-2">
          <Button
            type="button"
            variant="outline"
            onClick={() =>
              navigateTo(`/admin/audit-engagement/${engagementNumber}/planning/programs`)
            }
            className="h-7 text-xs px-2.5 flex items-center gap-1 text-blue-600 hover:text-blue-700 border-blue-200 hover:bg-blue-50 cursor-pointer"
          >
            <FileSpreadsheet size={12} />
            <span>Open Full Programs (SCR-223)</span>
            <ArrowRight size={11} />
          </Button>
          <span className="text-[10px] text-slate-400 font-mono hidden sm:inline">
            UID-200 §5.5, §8.3
          </span>
        </div>
      </div>

      <div className="space-y-2.5">
        {programs.map((prog) => (
          <div
            key={prog.id}
            className="rounded-lg border border-slate-200 bg-slate-50/50 p-3 space-y-2 text-xs transition hover:bg-slate-50"
          >
            <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-1.5">
              <div className="flex items-center gap-2">
                <span className="font-mono text-[10px] font-bold text-blue-700 bg-white px-1.5 py-0.2 rounded border border-blue-200">
                  {prog.id}
                </span>
                <span className="font-bold text-slate-900 text-xs">{prog.programTitle}</span>
              </div>

              <div className="flex items-center gap-1.5">
                <span className="rounded bg-indigo-50 px-1.5 py-0.2 text-[10px] font-bold text-indigo-700 border border-indigo-200">
                  {prog.proceduresCount} Procedures
                </span>
                <span className="rounded bg-slate-100 px-1.5 py-0.2 text-[10px] font-mono text-slate-600 border border-slate-200">
                  {prog.workingPaperRef}
                </span>
              </div>
            </div>

            <div className="grid grid-cols-1 gap-2 sm:grid-cols-2 text-[11px]">
              <div className="space-y-0.5">
                <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">
                  Target Audit Area & Sampling
                </span>
                <p className="font-medium text-slate-800">{prog.auditArea}</p>
                <p className="text-[10px] text-slate-500">{prog.samplingMethod}</p>
              </div>

              <div className="space-y-0.5 rounded bg-white p-2 border border-slate-200/70">
                <div className="flex items-center justify-between">
                  <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                    Assigned Auditor
                  </span>
                  <span className="font-mono text-[10px] text-slate-600 font-bold">
                    {prog.plannedHours} hrs planned
                  </span>
                </div>
                <div className="flex items-center gap-1 text-slate-800 font-semibold text-[11px] pt-0.5">
                  <UserCheck size={12} className="text-blue-600 shrink-0" />
                  <span>{prog.assignedAuditor}</span>
                </div>
              </div>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
