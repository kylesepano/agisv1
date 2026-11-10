import { useMemo } from "react";
import { ArrowLeft } from "lucide-react";
import { AdminLayout } from "../../../../layouts/admin/AdminLayout";
import { Button } from "../../../../components/ui/button";
import { navigateTo } from "../../../../routes";
import { localStore } from "../../../../lib/localStore";
import { defaultAuditPrograms } from "../programs/programs-data";

export function AdminProcedureDetailPage() {
  const currentPath = window.location.pathname;
  const pathParts = currentPath.split("/");
  // /admin/audit-engagement/:id/planning/procedures/:procedureId
  const engagementId = pathParts[3] || "AEMS-2026-010";
  const procedureId = pathParts[6] || pathParts[5] || "PROC-2026-BID-01";

  // Find procedure across stored programs
  const procedureInfo = useMemo(() => {
    const storedPrograms = localStore.getProgramsState(engagementId)?.programs || defaultAuditPrograms;
    for (const prog of storedPrograms) {
      const found = prog.procedures.find(
        (p) => p.id.toLowerCase() === procedureId.toLowerCase(),
      );
      if (found) {
        return {
          procedure: found,
          program: prog,
        };
      }
    }
    return {
      procedure: storedPrograms[0]?.procedures[0] || defaultAuditPrograms[0].procedures[0],
      program: storedPrograms[0] || defaultAuditPrograms[0],
    };
  }, [engagementId, procedureId]);

  const { procedure, program } = procedureInfo;

  return (
    <AdminLayout>
      <div className="space-y-2.5 pb-8">
        {/* Header Card */}
        <div className="rounded-lg border border-slate-200 bg-white p-3 shadow-sm space-y-2">
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
              <button
                type="button"
                onClick={() =>
                  navigateTo(`/admin/audit-engagement/${engagementId}`)
                }
                className="hover:text-blue-600 transition"
              >
                {engagementId}
              </button>
              <span>/</span>
              <button
                type="button"
                onClick={() =>
                  navigateTo(`/admin/audit-engagement/${engagementId}/planning`)
                }
                className="hover:text-blue-600 transition"
              >
                Planning Workspace (SCR-221)
              </button>
              <span>/</span>
              <button
                type="button"
                onClick={() =>
                  navigateTo(
                    `/admin/audit-engagement/${engagementId}/planning/programs`,
                  )
                }
                className="hover:text-blue-600 transition"
              >
                Audit Programs (SCR-223)
              </button>
              <span>/</span>
              <span className="text-slate-700 font-semibold">
                Procedure Detail (SCR-224 Scaffold)
              </span>
            </div>
            <span className="text-[10px] font-mono text-slate-400">
              MDS-200 v0.8 §7, §9.2 · UID-200 §8.3 SCR-224
            </span>
          </div>

          <div className="flex flex-col gap-2 pt-0.5 sm:flex-row sm:items-center sm:justify-between">
            <div className="flex items-center gap-2.5">
              <button
                type="button"
                onClick={() =>
                  navigateTo(
                    `/admin/audit-engagement/${engagementId}/planning/programs`,
                  )
                }
                className="grid h-8 w-8 shrink-0 place-items-center rounded-md border border-slate-200 bg-slate-50 text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition"
              >
                <ArrowLeft className="h-4 w-4" />
              </button>
              <div>
                <div className="flex items-center gap-1.5">
                  <span className="font-mono text-[10px] font-bold text-blue-700 bg-blue-50 px-1.5 py-0.2 rounded border border-blue-200">
                    SCR-224 Scaffold
                  </span>
                  <h1 className="text-[1.1rem] font-bold tracking-tight text-slate-900 leading-snug">
                    {procedure.id} — {procedure.procedureTitle}
                  </h1>
                </div>
                <p className="text-[11px] text-slate-500">
                  Detailed Audit Procedure specification under {program.programTitle} ({program.auditArea}).
                </p>
              </div>
            </div>

            <Button
              type="button"
              variant="outline"
              onClick={() =>
                navigateTo(
                  `/admin/audit-engagement/${engagementId}/planning/programs`,
                )
              }
              className="h-9 rounded-lg border-slate-200 text-xs font-semibold"
            >
              Return to Programs (SCR-223)
            </Button>
          </div>
        </div>

        {/* Procedure Detail Scaffold Card */}
        <div className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm space-y-4">
          <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-2.5">
            <div className="flex items-center gap-2">
              <span className="font-mono text-xs font-bold text-blue-700 bg-blue-50 px-2 py-0.5 rounded border border-blue-200">
                {procedure.id}
              </span>
              <span className="rounded bg-indigo-50 px-2 py-0.5 text-[11px] font-bold text-indigo-700 border border-indigo-200">
                {program.auditArea}
              </span>
              <span className="rounded bg-amber-50 px-2 py-0.5 text-[11px] font-bold text-amber-800 border border-amber-200">
                {procedure.riskMatrixItemId} Link
              </span>
            </div>
            <span className="rounded bg-slate-100 px-2 py-0.5 text-xs font-mono font-bold text-slate-700">
              {procedure.status}
            </span>
          </div>

          <div className="space-y-3 text-xs">
            {/* Purpose & PFD Stage */}
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
              <div className="rounded-lg bg-slate-50 p-3 border border-slate-200 space-y-1">
                <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">
                  Procedure Purpose (MDS-200 §7)
                </span>
                <p className="text-slate-800 leading-relaxed font-medium">
                  {procedure.purpose}
                </p>
              </div>

              <div className="rounded-lg bg-slate-50 p-3 border border-slate-200 space-y-1">
                <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">
                  Linked Process Flow Stage (SCR-221)
                </span>
                <p className="text-slate-800 leading-relaxed font-medium">
                  {procedure.pfdStageRef}
                </p>
              </div>
            </div>

            {/* Evidence Requirements */}
            <div className="rounded-lg bg-slate-50 p-3 border border-slate-200 space-y-1">
              <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">
                Required Working Paper Evidence
              </span>
              <p className="text-slate-800 leading-relaxed font-mono text-[11px]">
                {procedure.expectedEvidence}
              </p>
            </div>

            {/* Quantitative Scope & Schedule Attributes (MDS-200 §7) */}
            <div className="grid grid-cols-2 gap-2.5 sm:grid-cols-5 pt-1 font-mono text-xs">
              <div className="rounded border border-slate-200 p-2.5 bg-white space-y-0.5">
                <span className="text-[9px] text-slate-400 font-bold block uppercase">
                  Audit Method
                </span>
                <span className="font-bold text-blue-700">
                  {procedure.auditMethod}
                </span>
              </div>

              <div className="rounded border border-slate-200 p-2.5 bg-white space-y-0.5">
                <span className="text-[9px] text-slate-400 font-bold block uppercase">
                  Responsible Auditor
                </span>
                <span className="font-bold text-slate-800 truncate block">
                  {procedure.personResponsible}
                </span>
              </div>

              <div className="rounded border border-slate-200 p-2.5 bg-white space-y-0.5">
                <span className="text-[9px] text-slate-400 font-bold block uppercase">
                  Planned Man-Days
                </span>
                <span className="font-bold text-indigo-700">
                  {procedure.plannedManDays} Days
                </span>
              </div>

              <div className="rounded border border-slate-200 p-2.5 bg-white space-y-0.5">
                <span className="text-[9px] text-slate-400 font-bold block uppercase">
                  Working Paper Ref
                </span>
                <span className="font-bold text-purple-700">
                  {procedure.workingPaperRef}
                </span>
              </div>

              <div className="rounded border border-slate-200 p-2.5 bg-white space-y-0.5">
                <span className="text-[9px] text-slate-400 font-bold block uppercase">
                  Due Date
                </span>
                <span className="font-bold text-amber-800">
                  {procedure.dueDate}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </AdminLayout>
  );
}

export default AdminProcedureDetailPage;
