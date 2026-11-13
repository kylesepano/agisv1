import { useState } from "react";
import {
  Calendar,
  CheckCircle2,
  Lock,
  Shield,
} from "lucide-react";
import { Button } from "../../../../../../components/ui/button";
import { useToast } from "../../../../../../components/ui/toast";
import { navigateTo } from "../../../../../../routes";
import type { FullEngagementDetails } from "../../engagement-details-types";
import { AepStrategySection } from "./AepStrategySection";
import { AuditProgramsSection } from "./AuditProgramsSection";
import { EngagementRiskMatrixSection } from "./EngagementRiskMatrixSection";
import { PlanningSystemControlsCard } from "./PlanningSystemControlsCard";
import { ProcessFlowDocSection } from "./ProcessFlowDocSection";
import { localStore } from "../../../../../../lib/localStore";
import type { PlanningPackageState } from "./planning-types";

type PlanningWorkspaceProps = {
  details: FullEngagementDetails;
};

export function PlanningWorkspace({ details }: PlanningWorkspaceProps) {
  const { showToast } = useToast();
  const [packageState, setPackageState] = useState<PlanningPackageState>(() => {
    return localStore.getPlanningPackage(details.code);
  });

  // Check if gated: Phase 1 (Draft, no AEO issued)
  const isPhase1Gated = details.status === "Draft" || !details.aeo;

  const handleSaveDraft = () => {
    localStore.savePlanningPackage(details.code, packageState);
    showToast({
      title: "Planning Package Saved",
      description: `Draft v1.0 of Planning Package for ${details.code} saved successfully.`,
      tone: "info",
    });
  };

  const handleSubmitReview = () => {
    const updated = { ...packageState, status: "Under Review" as const };
    setPackageState(updated);
    localStore.savePlanningPackage(details.code, updated);
    showToast({
      title: "Planning Package Submitted",
      description: `Planning Package transmitted to ${packageState.reviewer} for supervisory review.`,
      tone: "success",
    });
  };

  const handleScheduleEntryConf = () => {
    navigateTo(`/admin/audit-engagement/${details.code}/conference/entry`);
    showToast({
      title: "Opening Conference Management",
      description: "Redirecting to SCR-225: Entry Conference & Stakeholder Logistics...",
      tone: "info",
    });
  };

  // If GATED (Draft / Phase 1): Show Phase 1 Gate
  if (isPhase1Gated) {
    return (
      <div className="rounded-xl border border-amber-200 bg-linear-to-b from-amber-50/80 to-white p-6 shadow-sm space-y-4">
        <div className="flex items-start gap-3.5">
          <div className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-amber-100 text-amber-800 border border-amber-200">
            <Lock className="h-5 w-5" />
          </div>
          <div className="space-y-1">
            <div className="flex items-center gap-2">
              <span className="font-mono text-[10px] font-bold text-amber-800 bg-amber-100 px-1.5 py-0.2 rounded border border-amber-300">
                MDS-200 §10.1 Lifecycle Gate
              </span>
              <h3 className="text-base font-bold text-amber-950">
                Phase 2 Audit Planning Package is Gated
              </h3>
            </div>
            <p className="text-xs text-amber-900 leading-relaxed max-w-2xl">
              Per MDS-200 §10.1, <strong>Audit Planning</strong> is Phase 2 of the 10-phase engagement lifecycle. This planning workspace (SCR-221, AEP SCR-222, and Audit Programs SCR-223/224) becomes accessible once Phase 1 (Engagement Initiation & Authorization) is completed and the official <strong>Audit Engagement Order (AEO)</strong> is issued.
            </p>
          </div>
        </div>

        {/* Action Callout Box */}
        <div className="rounded-lg border border-amber-200/80 bg-white p-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div className="text-xs space-y-0.5">
            <span className="font-bold text-slate-800 block">
              Required Upstream Action to Unlock:
            </span>
            <span className="text-slate-500">
              Current Status: <strong className="text-amber-700">Draft</strong> • Prepare, review, and issue the AEO in <strong>SCR-214</strong>.
            </span>
          </div>

          <Button
            type="button"
            className="h-9.5 px-4 text-xs font-semibold bg-blue-600 hover:bg-blue-700 text-white shadow-sm flex items-center gap-2 transition active:scale-[0.99] cursor-pointer shrink-0"
            onClick={() =>
              navigateTo(`/admin/audit-engagement/${details.code}/prepare-order`)
            }
          >
            <span>Go to Prepare AEO (SCR-214)</span>
          </Button>
        </div>
      </div>
    );
  }

  // Active / Phase 2+: Render Full SCR-221 Workspace
  return (
    <div className="space-y-3">
      {/* Top Planning Context Banner */}
      <div className="rounded-lg border border-slate-200 bg-slate-50/80 p-3 shadow-2xs space-y-2">
        <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200/80 pb-1.5">
          <div className="flex items-center gap-2">
            <Shield className="h-3.5 w-3.5 text-blue-600" />
            <span className="text-[11px] font-bold text-slate-800 uppercase tracking-wider">
              SCR-221 — Engagement Planning Package Coordinator (UID-200 §5.5)
            </span>
          </div>
          <div className="flex items-center gap-2">
            <span className="text-[10px] font-mono text-emerald-700 bg-emerald-50 px-1.5 py-0.2 rounded border border-emerald-200 font-bold">
              Issued AEO: {details.aeo?.aeoNumber}
            </span>
            <span className="text-[10px] text-slate-400 font-mono">
              Phase 2: Audit Planning
            </span>
          </div>
        </div>

        <div className="grid grid-cols-1 gap-2 md:grid-cols-3 text-xs">
          <div>
            <span className="text-[10px] font-semibold text-slate-400 uppercase tracking-wider block">
              Target Auditee
            </span>
            <span className="font-bold text-slate-900 block truncate">{details.office}</span>
            <span className="text-[11px] text-slate-500 block truncate">{details.title}</span>
          </div>

          <div>
            <span className="text-[10px] font-semibold text-slate-400 uppercase tracking-wider block">
              Lead Auditor & Supervision
            </span>
            <span className="font-bold text-slate-900 block truncate">
              {details.team.find((m) => m.role === "Lead Auditor")?.name || "Juan Dela Cruz"}
            </span>
            <span className="text-[11px] text-slate-500 block truncate">
              {details.team.length} Members in Roster
            </span>
          </div>

          <div>
            <span className="text-[10px] font-semibold text-slate-400 uppercase tracking-wider block">
              Fieldwork Planning Window
            </span>
            <div className="flex items-center gap-1 font-mono font-bold text-slate-800">
              <Calendar size={12} className="text-indigo-600" />
              <span>{details.startDate} ~ {details.endDate}</span>
            </div>
            <span className="text-[10px] text-slate-400 block">45 Man-Days Budgeted</span>
          </div>
        </div>
      </div>

      {/* Phase 3 Milestone Banner when Entry Conference Conducted */}
      {packageState.entryConference?.isConductedOrWaived && (
        <div className="rounded-lg border border-emerald-200 bg-emerald-50/70 p-3 shadow-2xs flex flex-wrap items-center justify-between gap-2 text-xs text-emerald-950">
          <div className="flex items-center gap-2">
            <CheckCircle2 size={16} className="text-emerald-600 shrink-0" />
            <div>
              <span className="font-bold text-emerald-900">
                Phase 3 Milestone Satisfied — Entry Conference Conducted & Documented ({packageState.entryConference.conferenceId})
              </span>
              <p className="text-[11px] text-emerald-800 leading-tight">
                Stakeholder communication protocols affirmed on {packageState.entryConference.date}. The engagement is cleared to proceed toward <strong>Audit Execution (Phase 4)</strong>.
              </p>
            </div>
          </div>
          <button
            type="button"
            onClick={() =>
              navigateTo(
                `/admin/audit-engagement/${details.code}/conference/entry`,
              )
            }
            className="rounded bg-white px-2.5 py-1 text-xs font-semibold text-emerald-800 border border-emerald-300 hover:bg-emerald-100/60 transition shadow-2xs cursor-pointer shrink-0"
          >
            View Entry Conference (SCR-225) →
          </button>
        </div>
      )}

      {/* Main 2-Column Planning Grid */}
      <div className="grid grid-cols-1 gap-3 lg:grid-cols-12">
        {/* Left Column (8 cols): Controlled Artifacts */}
        <div className="space-y-3 lg:col-span-8">
          {/* 1. Process Flow Documentation (PFD) */}
          <ProcessFlowDocSection flows={packageState.processFlows} />

          {/* 2. Engagement Risk Matrix (ERM) */}
          <EngagementRiskMatrixSection risks={packageState.risks} />

          {/* 3. Audit Engagement Plan (AEP) & KPIs (SCR-222 Coordinator) */}
          <AepStrategySection
            aep={packageState.aep}
            engagementNumber={details.code}
          />

          {/* 4. Audit Programs & Procedures Summary (SCR-223/224 Coordinator) */}
          <AuditProgramsSection
            programs={packageState.auditPrograms}
            engagementNumber={details.code}
          />
        </div>

        {/* Right Column (4 cols): System Controls & Planning Readiness Checklist */}
        <div className="space-y-3 lg:col-span-4">
          <div className="sticky top-4">
            <PlanningSystemControlsCard
              packageState={packageState}
              engagementNumber={details.code}
              onSaveDraft={handleSaveDraft}
              onSubmitReview={handleSubmitReview}
              onScheduleEntryConf={handleScheduleEntryConf}
            />
          </div>
        </div>
      </div>
    </div>
  );
}
