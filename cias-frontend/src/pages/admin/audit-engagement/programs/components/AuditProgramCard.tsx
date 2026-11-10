import {
  Calendar,
  Compass,
  FileSpreadsheet,
  Link,
  Scale,
} from "lucide-react";
import type { AuditProgram } from "../programs-types";
import { ProcedureTableRow } from "./ProcedureTableRow";

type AuditProgramCardProps = {
  program: AuditProgram;
  engagementNumber: string;
};

export function AuditProgramCard({
  program,
  engagementNumber,
}: AuditProgramCardProps) {
  const totalManDays = program.procedures.reduce(
    (sum, p) => sum + p.plannedManDays,
    0,
  );

  return (
    <div className="rounded-lg border border-slate-200 bg-white p-3.5 shadow-sm space-y-3">
      {/* Program Header */}
      <div className="space-y-2 border-b border-slate-100 pb-2.5">
        <div className="flex flex-wrap items-center justify-between gap-2">
          <div className="flex flex-wrap items-center gap-2">
            <span className="font-mono text-xs font-bold text-blue-700 bg-blue-50 px-2 py-0.5 rounded border border-blue-200">
              {program.id}
            </span>
            <span className="rounded bg-indigo-50 px-2 py-0.5 text-[10px] font-bold text-indigo-700 border border-indigo-200">
              {program.auditArea}
            </span>
            <span className="rounded bg-amber-50 px-1.5 py-0.2 text-[10px] font-bold text-amber-700 border border-amber-200">
              {program.status} ({program.version})
            </span>
          </div>

          <div className="flex items-center gap-2 text-xs font-mono">
            <span className="rounded bg-slate-100 px-2 py-0.5 font-bold text-slate-700">
              {program.procedures.length} Procedures
            </span>
            <span className="rounded bg-emerald-50 px-2 py-0.5 font-bold text-emerald-700 border border-emerald-200">
              {totalManDays} Man-Days Budgeted
            </span>
          </div>
        </div>

        <h3 className="text-sm font-bold text-slate-900 leading-snug">
          {program.programTitle}
        </h3>

        {/* Program Type & Period Sub-bar (MDS-200 §7 Entity Fields) */}
        <div className="flex flex-wrap items-center gap-3 text-[11px] bg-slate-50/90 rounded-md px-2.5 py-1.5 border border-slate-200/80">
          <div className="flex items-center gap-1.5">
            <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
              Type of Audit (MDS-200 §7):
            </span>
            <span className="font-semibold text-slate-800">
              {program.auditType}
            </span>
          </div>
          <span className="text-slate-300 hidden sm:inline">•</span>
          <div className="flex items-center gap-1.5">
            <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
              Audit Period Covered (MDS-200 §7):
            </span>
            <span className="font-mono font-semibold text-slate-800 flex items-center gap-1">
              <Calendar size={11} className="text-indigo-600" />
              {program.auditPeriod}
            </span>
          </div>
        </div>
      </div>

      {/* Description & Scope Metadata */}
      <div className="space-y-2 text-xs">
        <p className="text-[11px] text-slate-700 leading-relaxed rounded-md bg-slate-50/70 p-2.5 border border-slate-100">
          {program.description}
        </p>

        {/* 3-Column Context Grid: Objectives Trace, Criteria, and Linked RMIs */}
        <div className="grid grid-cols-1 gap-2.5 sm:grid-cols-3 text-[11px]">
          {/* Objectives Trace */}
          <div className="rounded-md bg-white p-2.5 border border-slate-200 space-y-1">
            <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider flex items-center gap-1">
              <Compass size={11} className="text-blue-600" />
              Objectives Alignment (SCR-222)
            </span>
            <p className="text-slate-800 leading-snug">{program.objectivesTrace}</p>
          </div>

          {/* Criteria Framework Refs */}
          <div className="rounded-md bg-white p-2.5 border border-slate-200 space-y-1">
            <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider flex items-center gap-1">
              <Scale size={11} className="text-blue-600" />
              Criteria Framework (SCR-222)
            </span>
            <ul className="space-y-0.5 text-slate-700 text-[10px] list-disc list-inside">
              {program.criteriaFrameworkRefs.map((c, i) => (
                <li key={i} className="truncate">
                  {c}
                </li>
              ))}
            </ul>
          </div>

          {/* Linked Risk Matrix Items */}
          <div className="rounded-md bg-white p-2.5 border border-slate-200 space-y-1">
            <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider flex items-center gap-1">
              <Link size={11} className="text-amber-600" />
              Linked Risk Matrix Items (SCR-221)
            </span>
            <div className="flex flex-wrap gap-1 pt-0.5">
              {program.linkedRmiIds.map((rmi, i) => (
                <span
                  key={i}
                  className="rounded bg-amber-50 px-1.5 py-0.5 text-[10px] font-mono font-bold text-amber-800 border border-amber-200"
                >
                  {rmi}
                </span>
              ))}
            </div>
            <span className="text-[9px] text-slate-400 block pt-0.5">
              Mapped per MDS-200 §19 OD-008
            </span>
          </div>
        </div>

        {/* Governance & Signatories Bar */}
        <div className="rounded-md bg-slate-50/90 p-2 border border-slate-200/80 flex flex-wrap items-center justify-between gap-2 text-[10px] font-mono text-slate-600">
          <div className="flex items-center gap-1.5 truncate">
            <span className="text-slate-400 font-sans font-semibold">Prep:</span>
            <span className="font-bold text-slate-800 truncate">
              {program.preparedBy}
            </span>
          </div>

          <div className="flex items-center gap-1.5 truncate">
            <span className="text-slate-400 font-sans font-semibold">Review:</span>
            <span className="font-bold text-slate-800 truncate">
              {program.reviewedBy}
            </span>
          </div>

          <div className="flex items-center gap-1.5 truncate">
            <span className="text-slate-400 font-sans font-semibold">Approve:</span>
            <span className="font-bold text-slate-800 truncate">
              {program.approvedBy}
            </span>
            <span className="rounded bg-amber-100 text-amber-800 px-1 py-0.2 text-[9px] font-bold">
              OD-001
            </span>
          </div>

          <div className="flex items-center gap-1">
            <Calendar size={11} className="text-slate-400" />
            <span>Effective {program.effectiveDate}</span>
          </div>
        </div>
      </div>

      {/* Nested Detailed Audit Procedures (SCR-224 Coordinator) */}
      <div className="space-y-2 pt-2 border-t border-slate-100">
        <div className="flex items-center justify-between">
          <h4 className="text-xs font-bold text-slate-800 flex items-center gap-1.5">
            <FileSpreadsheet size={13} className="text-blue-600" />
            <span>Detailed Audit Testing Procedures (MDS-200 §7, SCR-224)</span>
          </h4>
          <span className="text-[10px] text-slate-400 font-mono">
            {program.procedures.length} Procedures Configured
          </span>
        </div>

        <div className="space-y-2">
          {program.procedures.map((proc) => (
            <ProcedureTableRow
              key={proc.id}
              procedure={proc}
              engagementNumber={engagementNumber}
            />
          ))}
        </div>
      </div>
    </div>
  );
}
