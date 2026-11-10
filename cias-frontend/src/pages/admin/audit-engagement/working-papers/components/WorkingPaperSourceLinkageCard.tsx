import { useMemo } from "react";
import {
  ArrowUpRight,
  Bookmark,
  FileCheck2,
  FileSpreadsheet,
  Link2,
  ShieldAlert,
} from "lucide-react";
import { navigateTo } from "../../../../../routes";
import { localStore } from "../../../../../lib/localStore";
import type { WorkingPaper } from "../working-papers-types";

type WorkingPaperSourceLinkageCardProps = {
  workingPaper: WorkingPaper;
  engagementNumber: string;
};

export function WorkingPaperSourceLinkageCard({
  workingPaper,
  engagementNumber,
}: WorkingPaperSourceLinkageCardProps) {
  const fieldworkState = useMemo(() => {
    return localStore.getFieldworkState(engagementNumber);
  }, [engagementNumber]);

  const matchingFwr = useMemo(() => {
    if (workingPaper.sourceFieldworkRecordId) {
      const byId = fieldworkState?.records?.find(
        (f) => f.id === workingPaper.sourceFieldworkRecordId,
      );
      if (byId) return byId;
    }
    // Match by workingPaperRef or procedureId
    return fieldworkState?.records?.find(
      (f) =>
        f.workingPaperRef === workingPaper.id ||
        f.procedureId === workingPaper.procedureId ||
        (f.id.includes("2026-020") && workingPaper.id.includes("2026-020")),
    );
  }, [fieldworkState, workingPaper]);

  const sourceFwrId =
    workingPaper.sourceFieldworkRecordId || matchingFwr?.id;
  const sourceFwrType =
    workingPaper.sourceFieldworkType ||
    matchingFwr?.type ||
    "Physical Inspection & Sampling";

  return (
    <div className="rounded-lg border border-purple-200 bg-purple-50/50 p-3.5 shadow-2xs space-y-3">
      {/* Rule 13 Invariant Header */}
      <div className="flex flex-wrap items-center justify-between gap-2 border-b border-purple-200/80 pb-2">
        <div className="flex items-center gap-2">
          <FileSpreadsheet className="h-4 w-4 text-purple-700" />
          <h4 className="text-xs font-bold text-purple-950">
            Source Cross-Reference & Linkage Architecture (Rule 13 Invariant)
          </h4>
        </div>
        <span className="font-mono text-[10px] font-bold text-purple-800 bg-white px-2 py-0.5 rounded border border-purple-300">
          MDS-200 §11 Rule 13
        </span>
      </div>

      <p className="text-[11px] text-purple-900 leading-snug">
        <strong>Rule 13 Principle:</strong> All material audit work and conclusions shall be formally documented in Working Papers. Fieldwork Records (SCR-227) feed directly into this Working Paper as primary source observation evidence.
      </p>

      {/* Cross-Reference Grid */}
      <div className="grid grid-cols-1 gap-2.5 sm:grid-cols-2 lg:grid-cols-4 pt-0.5 text-xs">
        {/* 1. Source Fieldwork Record Link */}
        <div className="rounded-md border border-purple-200/90 bg-white p-2.5 space-y-1">
          <div className="flex items-center justify-between">
            <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider flex items-center gap-1">
              <FileCheck2 size={11} className="text-blue-600" />
              <span>Source Fieldwork (SCR-227)</span>
            </span>
          </div>
          {sourceFwrId ? (
            <div>
              <div className="flex items-center justify-between">
                <span className="font-mono text-xs font-bold text-blue-700">
                  {sourceFwrId}
                </span>
                <button
                  type="button"
                  onClick={() =>
                    navigateTo(
                      `/admin/audit-engagement/${engagementNumber}/fieldwork-records/${sourceFwrId}`,
                    )
                  }
                  className="inline-flex items-center gap-0.5 text-[10px] font-semibold text-blue-600 hover:text-blue-800 hover:underline cursor-pointer"
                  title="Open Source Fieldwork Record"
                >
                  <span>View</span>
                  <ArrowUpRight size={10} />
                </button>
              </div>
              <p className="text-[10px] text-slate-500 truncate">
                {sourceFwrType}
              </p>
            </div>
          ) : (
            <span className="text-[11px] text-slate-400 italic">
              No FWR linked directly
            </span>
          )}
        </div>

        {/* 2. Procedure ID Link (SCR-224) */}
        <div className="rounded-md border border-purple-200/90 bg-white p-2.5 space-y-1">
          <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider flex items-center gap-1">
            <Link2 size={11} className="text-indigo-600" />
            <span>Procedure ID (SCR-224)</span>
          </span>
          <div className="flex items-center justify-between">
            <span className="font-mono text-xs font-bold text-indigo-700">
              {workingPaper.procedureId}
            </span>
            <button
              type="button"
              onClick={() =>
                navigateTo(
                  `/admin/audit-engagement/${engagementNumber}/planning/procedures/${workingPaper.procedureId}`,
                )
              }
              className="inline-flex items-center gap-0.5 text-[10px] font-semibold text-indigo-600 hover:text-indigo-800 hover:underline cursor-pointer"
              title="Open Procedure in Planning"
            >
              <span>Detail</span>
              <ArrowUpRight size={10} />
            </button>
          </div>
          <p className="text-[10px] text-slate-500 truncate" title={workingPaper.procedureTitle}>
            {workingPaper.procedureTitle}
          </p>
        </div>

        {/* 3. Risk Matrix Item Link (SCR-221) */}
        <div className="rounded-md border border-purple-200/90 bg-white p-2.5 space-y-1">
          <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider flex items-center gap-1">
            <ShieldAlert size={11} className="text-amber-600" />
            <span>Risk Matrix Item (SCR-221)</span>
          </span>
          <div className="flex items-center justify-between">
            <span className="font-mono text-xs font-bold text-amber-700">
              {workingPaper.riskMatrixItemId}
            </span>
            <button
              type="button"
              onClick={() =>
                navigateTo(
                  `/admin/audit-engagement/${engagementNumber}/define-scope`,
                )
              }
              className="inline-flex items-center gap-0.5 text-[10px] font-semibold text-amber-600 hover:text-amber-800 hover:underline cursor-pointer"
              title="Open Risk Assessment Scope"
            >
              <span>Scope</span>
              <ArrowUpRight size={10} />
            </button>
          </div>
          <p className="text-[10px] text-slate-500 truncate" title={workingPaper.riskMatrixTitle}>
            {workingPaper.riskMatrixTitle}
          </p>
        </div>

        {/* 4. Planned Reference Link (SCR-222/223) */}
        <div className="rounded-md border border-purple-200/90 bg-white p-2.5 space-y-1">
          <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider flex items-center gap-1">
            <Bookmark size={11} className="text-emerald-600" />
            <span>Planned Ref (SCR-222/223)</span>
          </span>
          <div className="flex items-center justify-between">
            <span className="font-mono text-xs font-bold text-emerald-700 truncate max-w-[130px]" title={workingPaper.plannedReference}>
              {workingPaper.plannedReference}
            </span>
            <button
              type="button"
              onClick={() =>
                navigateTo(
                  `/admin/audit-engagement/${engagementNumber}/planning/programs`,
                )
              }
              className="inline-flex items-center gap-0.5 text-[10px] font-semibold text-emerald-600 hover:text-emerald-800 hover:underline cursor-pointer"
              title="Open Audit Programs"
            >
              <span>Program</span>
              <ArrowUpRight size={10} />
            </button>
          </div>
          <p className="text-[10px] text-slate-500 truncate" title={workingPaper.auditArea}>
            {workingPaper.auditArea}
          </p>
        </div>
      </div>
    </div>
  );
}
