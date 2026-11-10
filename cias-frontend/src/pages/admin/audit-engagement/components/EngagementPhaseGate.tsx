import {
  ArrowRight,
  CheckCircle2,
  FolderLock,
  Lock,
  ShieldAlert,
} from "lucide-react";
import { Button } from "../../../../components/ui/button";
import { navigateTo } from "../../../../routes";
import { evaluateEngagementGating } from "../../../../lib/engagementPhaseGating";

type EngagementPhaseGateProps = {
  engagementNumber: string;
  requiredPhaseName: string;
  requiredPhaseNumber: number;
  screenTitle: string;
  screenCitation?: string;
};

export function EngagementPhaseGate({
  engagementNumber,
  requiredPhaseName,
  requiredPhaseNumber,
  screenTitle,
}: EngagementPhaseGateProps) {
  const gate = evaluateEngagementGating(engagementNumber);
  const engagement = gate.engagement;

  const engagementTitle =
    engagement?.auditArea ||
    engagement?.subject ||
    "Audit Engagement";
  const office = engagement?.office || "Auditee Department";

  return (
    <div className="space-y-4">
      {/* Gating Alert Banner */}
      <div className="rounded-xl border border-amber-200 bg-gradient-to-r from-amber-50 via-amber-50/60 to-white p-5 shadow-sm space-y-4">
        <div className="flex items-start gap-3.5">
          <div className="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-amber-100 text-amber-700 border border-amber-200">
            <ShieldAlert className="h-5 w-5" />
          </div>
          <div className="space-y-1 flex-1">
            <div className="flex items-center gap-2 flex-wrap">
              <span className="font-mono text-[10px] font-bold text-amber-800 bg-amber-100 px-2 py-0.5 rounded border border-amber-300">
                PHASE ACCESS RESTRICTED
              </span>
              <span className="text-xs font-semibold text-slate-600">
                Prerequisites Required for {requiredPhaseName}
              </span>
            </div>
            <h2 className="text-base font-bold text-slate-900 leading-snug">
              {screenTitle} is locked for {engagementNumber}
            </h2>
            <p className="text-xs text-slate-600 leading-relaxed max-w-3xl">
              Under City Internal Audit Services governance standards (<strong>MDS-200 Module Rule 11</strong>), this screen becomes accessible once upstream lifecycle milestones are formally completed. This engagement is currently at <strong>{gate.currentPhaseName}</strong>.
            </p>
          </div>
        </div>

        {/* Active Context Card */}
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-2.5 rounded-lg border border-amber-200/80 bg-white/90 p-3 text-xs">
          <div>
            <span className="text-[10px] font-semibold text-slate-400 uppercase tracking-wider block">
              Engagement Title
            </span>
            <span className="font-medium text-slate-800 line-clamp-1 mt-0.5">
              {engagementTitle}
            </span>
          </div>
          <div>
            <span className="text-[10px] font-semibold text-slate-400 uppercase tracking-wider block">
              Auditee Office
            </span>
            <span className="font-medium text-slate-800 line-clamp-1 mt-0.5">
              {office}
            </span>
          </div>
          <div>
            <span className="text-[10px] font-semibold text-slate-400 uppercase tracking-wider block">
              Current Status
            </span>
            <span className="inline-flex items-center gap-1 font-semibold text-amber-700 bg-amber-50 px-2 py-0.5 rounded border border-amber-200 text-[11px] mt-0.5">
              <Lock size={11} />
              {engagement?.status || "Draft"} ({gate.currentPhaseName})
            </span>
          </div>
        </div>

        {/* Milestone Gating Progression Stepper */}
        <div className="space-y-2 pt-1">
          <h3 className="text-xs font-bold text-slate-800">
            Lifecycle Prerequisite Sequence
          </h3>
          <div className="space-y-2">
            {/* Step 1 */}
            <div className={`flex items-start gap-3 rounded-lg border p-3 text-xs transition ${
              gate.isPhase1Complete
                ? "border-emerald-200 bg-emerald-50/50"
                : "border-blue-200 bg-blue-50/50"
            }`}>
              <div className="mt-0.5">
                {gate.isPhase1Complete ? (
                  <CheckCircle2 className="h-4 w-4 text-emerald-600" />
                ) : (
                  <div className="grid h-4 w-4 place-items-center rounded-full bg-blue-600 text-white font-mono text-[9px] font-bold">
                    1
                  </div>
                )}
              </div>
              <div className="flex-1">
                <div className="flex items-center justify-between">
                  <span className="font-bold text-slate-800">
                    Phase 1: Engagement Initiation & AEO Issuance (SCR-211 → SCR-214)
                  </span>
                  <span className={`text-[10px] font-semibold px-1.5 py-0.2 rounded border ${
                    gate.isPhase1Complete
                      ? "text-emerald-700 bg-emerald-100/60 border-emerald-200"
                      : "text-blue-700 bg-blue-100/60 border-blue-200"
                  }`}>
                    {gate.isPhase1Complete ? "Completed" : "In Progress (Current)"}
                  </span>
                </div>
                <p className="text-[11px] text-slate-600 mt-0.5">
                  Define engagement scope, confirm team collective sufficiency & independence declarations, and formally issue the Audit Engagement Order (AEO).
                </p>
              </div>
            </div>

            {/* Step 2 */}
            <div className={`flex items-start gap-3 rounded-lg border p-3 text-xs transition ${
              gate.isPhase2Complete
                ? "border-emerald-200 bg-emerald-50/50"
                : gate.isPhase1Complete
                ? "border-blue-200 bg-blue-50/50"
                : "border-slate-200 bg-slate-50/50 text-slate-400"
            }`}>
              <div className="mt-0.5">
                {gate.isPhase2Complete ? (
                  <CheckCircle2 className="h-4 w-4 text-emerald-600" />
                ) : (
                  <div className={`grid h-4 w-4 place-items-center rounded-full font-mono text-[9px] font-bold ${
                    gate.isPhase1Complete ? "bg-blue-600 text-white" : "bg-slate-300 text-slate-600"
                  }`}>
                    2
                  </div>
                )}
              </div>
              <div className="flex-1">
                <div className="flex items-center justify-between">
                  <span className={`font-bold ${gate.isPhase1Complete ? "text-slate-800" : "text-slate-500"}`}>
                    Phase 2: Audit Planning & Program Development (SCR-221 → SCR-224)
                  </span>
                  <span className={`text-[10px] font-semibold px-1.5 py-0.2 rounded border ${
                    gate.isPhase2Complete
                      ? "text-emerald-700 bg-emerald-100/60 border-emerald-200"
                      : gate.isPhase1Complete
                      ? "text-blue-700 bg-blue-100/60 border-blue-200"
                      : "text-slate-500 bg-slate-100 border-slate-200"
                  }`}>
                    {gate.isPhase2Complete ? "Completed" : gate.isPhase1Complete ? "Unlocked" : "Locked"}
                  </span>
                </div>
                <p className="text-[11px] text-slate-500 mt-0.5">
                  Prepare Audit Engagement Plan (AEP), establish audit methodology, and detail audit programs and substantive testing procedures.
                </p>
              </div>
            </div>

            {/* Step 3 */}
            <div className={`flex items-start gap-3 rounded-lg border p-3 text-xs transition ${
              gate.isPhase3Complete
                ? "border-emerald-200 bg-emerald-50/50"
                : gate.isPhase1Complete
                ? "border-amber-200 bg-amber-50/50"
                : "border-slate-200 bg-slate-50/50 text-slate-400"
            }`}>
              <div className="mt-0.5">
                {gate.isPhase3Complete ? (
                  <CheckCircle2 className="h-4 w-4 text-emerald-600" />
                ) : (
                  <div className={`grid h-4 w-4 place-items-center rounded-full font-mono text-[9px] font-bold ${
                    gate.isPhase1Complete ? "bg-amber-600 text-white" : "bg-slate-300 text-slate-600"
                  }`}>
                    3
                  </div>
                )}
              </div>
              <div className="flex-1">
                <div className="flex items-center justify-between">
                  <span className={`font-bold ${gate.isPhase1Complete ? "text-slate-800" : "text-slate-500"}`}>
                    Phase 3: Entry Conference & Auditee Protocol (SCR-225 / Rule 11)
                  </span>
                  <span className={`text-[10px] font-semibold px-1.5 py-0.2 rounded border ${
                    gate.isPhase3Complete
                      ? "text-emerald-700 bg-emerald-100/60 border-emerald-200"
                      : gate.isPhase1Complete
                      ? "text-amber-700 bg-amber-100/60 border-amber-200"
                      : "text-slate-500 bg-slate-100 border-slate-200"
                  }`}>
                    {gate.isPhase3Complete ? "Completed / Waived" : gate.isPhase1Complete ? "Mandatory Gate" : "Locked"}
                  </span>
                </div>
                <p className="text-[11px] text-slate-500 mt-0.5">
                  Conduct opening conference with auditee leadership or log an approved waiver justification before any field testing begins.
                </p>
              </div>
            </div>

            {/* Step 4 */}
            <div className={`flex items-start gap-3 rounded-lg border p-3 text-xs transition ${
              requiredPhaseNumber === 4
                ? "border-indigo-300 bg-indigo-50/40"
                : "border-slate-200 bg-slate-50/50 text-slate-400"
            }`}>
              <div className="mt-0.5">
                <FolderLock className="h-4 w-4 text-indigo-600" />
              </div>
              <div className="flex-1">
                <div className="flex items-center justify-between">
                  <span className="font-bold text-slate-800">
                    Phase 4: Fieldwork Execution Workspaces (SCR-226 → SCR-229)
                  </span>
                  <span className="text-[10px] font-bold text-indigo-700 bg-indigo-100/60 border border-indigo-200 px-1.5 py-0.2 rounded">
                    Target Destination (Locked)
                  </span>
                </div>
                <p className="text-[11px] text-slate-500 mt-0.5">
                  Execute fieldwork procedures, record observations, compile working papers, and custody electronic audit evidence.
                </p>
              </div>
            </div>
          </div>
        </div>

        {/* Action Row */}
        <div className="flex items-center justify-between flex-wrap gap-2.5 pt-2 border-t border-amber-200">
          <Button
            className="h-9 px-4 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold shadow-sm flex items-center gap-1.5 transition active:scale-[0.99] cursor-pointer"
            onClick={() => navigateTo(gate.currentStepRoute)}
          >
            <span>Continue at Current Step: {gate.currentStepTitle}</span>
            <ArrowRight className="h-4 w-4" />
          </Button>

          <Button
            className="h-9 px-3.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold flex items-center gap-1.5 transition active:scale-[0.99] cursor-pointer"
            variant="outline"
            onClick={() =>
              navigateTo(`/admin/audit-engagement/${engagementNumber}`)
            }
          >
            <span>Return to Engagement Overview (SCR-220)</span>
          </Button>
        </div>
      </div>
    </div>
  );
}
