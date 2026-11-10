import { useMemo } from "react";
import { ArrowLeft, ArrowRight } from "lucide-react";
import { AdminLayout } from "../../../../layouts/admin/AdminLayout";
import { Button } from "../../../../components/ui/button";
import { navigateTo } from "../../../../routes";
import { ExecutionWorkspace } from "./ExecutionWorkspace";
import { localStore } from "../../../../lib/localStore";
import { evaluateEngagementGating } from "../../../../lib/engagementPhaseGating";
import { EngagementPhaseGate } from "../components/EngagementPhaseGate";

export { ExecutionWorkspace } from "./ExecutionWorkspace";

export function AdminExecutionPage() {
  const currentPath = window.location.pathname;
  const pathParts = currentPath.split("/");
  const engagementId = pathParts[3] || "AEMS-2026-010";

  // Resolve matching engagement from localStore
  const existingEngagement = useMemo(() => {
    return localStore.getEngagement(engagementId);
  }, [engagementId]);

  const engagementNumber = existingEngagement?.code || engagementId;
  const engagementTitle =
    existingEngagement?.auditArea ||
    existingEngagement?.subject ||
    "Audit Execution";
  const office = existingEngagement?.office || "City General Services Office";

  // Enforce Phase Gating (MDS-200 Module Rule 11)
  const gate = evaluateEngagementGating(engagementNumber);

  return (
    <AdminLayout>
      <div className="space-y-2.5 pb-8">
        {/* Top Unified Header Card */}
        <div className="rounded-lg border border-slate-200 bg-white p-3 shadow-sm space-y-2">
          {/* Breadcrumb row */}
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
                  navigateTo(`/admin/audit-engagement/${engagementNumber}`)
                }
                className="hover:text-blue-600 transition"
              >
                {engagementNumber}
              </button>
              <span>/</span>
              <span className="text-slate-700 font-semibold">
                Execution Workspace (SCR-226)
              </span>
            </div>
            <span className="text-[10px] font-mono text-slate-400 hidden sm:inline">
              MDS-200 v0.8 §7, §9.3, §10.1 · UID-200 v0.5 §8.3 · Phase 4 Audit Execution · Hub Screen
            </span>
          </div>

          {/* Main Title and Action Group */}
          <div className="flex flex-col gap-2 pt-0.5 sm:flex-row sm:items-center sm:justify-between">
            <div className="flex items-center gap-2.5">
              <button
                type="button"
                onClick={() =>
                  navigateTo(`/admin/audit-engagement/${engagementNumber}`)
                }
                className="grid h-8 w-8 shrink-0 place-items-center rounded-md border border-slate-200 bg-slate-50 text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition"
                title="Back to Engagement Details"
              >
                <ArrowLeft className="h-4 w-4" />
              </button>
              <div>
                <div className="flex items-center gap-1.5">
                  <span className="font-mono text-[10px] font-bold text-blue-700 bg-blue-50 px-1.5 py-0.2 rounded border border-blue-200">
                    SCR-226
                  </span>
                  <h1 className="text-[1.1rem] font-bold tracking-tight text-slate-900 leading-snug">
                    Audit Execution Workspace Hub
                  </h1>
                </div>
                <p className="text-[11px] text-slate-500">
                  Manage fieldwork procedures, tasks, supervisory review notes, progress assessment, and blockers for <strong>{engagementNumber}</strong>.
                </p>
              </div>
            </div>

            {/* Header Actions */}
            <div className="flex items-center gap-2 shrink-0">
              <Button
                type="button"
                variant="outline"
                className="h-10 rounded-lg border-slate-200 px-3.5 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition active:scale-[0.99]"
                onClick={() =>
                  navigateTo(`/admin/audit-engagement/${engagementNumber}`)
                }
              >
                Return to Overview (SCR-220)
              </Button>

              <Button
                type="button"
                className="h-10 rounded-lg px-4 text-xs font-semibold bg-blue-600 hover:bg-blue-700 text-white shadow-sm flex items-center gap-2 transition active:scale-[0.99] cursor-pointer"
                onClick={() =>
                  navigateTo(
                    `/admin/audit-engagement/${engagementNumber}/execution/records`,
                  )
                }
              >
                <span>Open Fieldwork Records (SCR-227)</span>
                <ArrowRight className="h-4 w-4" />
              </Button>
            </div>
          </div>
        </div>

        {/* Execution Workspace Content or Gated View */}
        {!gate.isPhase3Complete ? (
          <EngagementPhaseGate
            engagementNumber={engagementNumber}
            requiredPhaseName="Phase 4: Fieldwork Execution"
            requiredPhaseNumber={4}
            screenTitle="Execution Workspace (SCR-226)"
          />
        ) : (
          <ExecutionWorkspace
            engagementNumber={engagementNumber}
            engagementTitle={engagementTitle}
            office={office}
            isGated={false}
          />
        )}
      </div>
    </AdminLayout>
  );
}

export default AdminExecutionPage;
