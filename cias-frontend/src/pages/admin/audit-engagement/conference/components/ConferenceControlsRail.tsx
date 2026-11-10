import {
  ArrowLeft,
  CheckCircle2,
  FileCheck2,
  Save,
  Send,
  Shield,
  XCircle,
} from "lucide-react";
import { Button } from "../../../../../components/ui/button";
import { navigateTo } from "../../../../../routes";
import type { ConferenceFormState } from "../conference-types";

type ConferenceControlsRailProps = {
  formState: ConferenceFormState;
  isReady: boolean;
  onToggleWaiverMode: (val: boolean) => void;
  onSaveDraft: () => void;
  onSubmitFinalize: () => void;
};

export function ConferenceControlsRail({
  formState,
  isReady,
  onToggleWaiverMode,
  onSaveDraft,
  onSubmitFinalize,
}: ConferenceControlsRailProps) {
  const isWaiver = formState.waiver.isWaiverActive;

  const standardChecks = [
    {
      label: "Synthesized Briefing Paper ready",
      valid: !!formState.briefingPaper.aeoRef,
      detail: "Referenced",
    },
    {
      label: "Schedule, Time & Venue defined",
      valid:
        (formState.scheduledDate || "").length > 0 &&
        (formState.venueOrLink || "").trim().length >= 3,
      detail: `${formState.mode}`,
    },
    {
      label: "Conference Agenda topics set",
      valid: (formState.agendaText || "").trim().length >= 10,
      detail: "Formulated",
    },
    {
      label: "Participants confirmed (CIAS & Auditee)",
      valid: formState.participants.length >= 2,
      detail: `${formState.participants.length} rostered`,
    },
    {
      label: "Minutes & Key Agreements recorded",
      valid:
        (formState.minutesAndNotes || "").trim().length >= 20 &&
        (formState.keyAgreements || "").trim().length >= 10,
      detail: "Documented",
    },
  ];

  const waiverChecks = [
    {
      label: "Waiver justification reason documented",
      valid: (formState.waiver.waiverReason || "").trim().length >= 10,
      detail: "Justified",
    },
    {
      label: "Alternative commencement procedure specified",
      valid: (formState.waiver.alternativeProcedure || "").trim().length >= 10,
      detail: "Specified",
    },
    {
      label: "Approving authority assigned (OD-001)",
      valid: (formState.waiver.approvingAuthority || "").trim().length >= 2,
      detail: "OD-001 Provisional",
    },
    {
      label: "Special authorization clearance affirmed",
      valid: formState.waiver.isApproved,
      detail: formState.waiver.isApproved ? "Affirmed" : "Pending check",
    },
  ];

  const checks = isWaiver ? waiverChecks : standardChecks;
  const passedCount = checks.filter((c) => c.valid).length;

  return (
    <div className="rounded-lg border border-slate-200 bg-white p-3.5 shadow-sm space-y-3.5">
      <div className="flex items-center justify-between border-b border-slate-100 pb-2">
        <div className="flex items-center gap-1.5 font-bold text-slate-800 text-xs">
          <Shield size={14} className="text-blue-600" />
          <span>Conference Controls (§7, §11)</span>
        </div>
        <span className="rounded bg-blue-50 px-1.5 py-0.2 text-[10px] font-mono font-bold text-blue-700">
          SCR-225
        </span>
      </div>

      {/* Engagement Identity Box */}
      <div className="rounded-lg border border-slate-100 bg-slate-50/70 p-2.5 space-y-1.5 text-xs">
        <div className="flex items-center justify-between">
          <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
            Engagement No.
          </span>
          <span className="font-mono font-bold text-blue-700 text-xs">
            {formState.engagementNumber}
          </span>
        </div>

        <div className="flex items-center justify-between">
          <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
            Lifecycle Milestone
          </span>
          <span className="rounded bg-indigo-50 px-1.5 py-0.2 text-[10px] font-bold text-indigo-700 border border-indigo-200">
            Phase 3 • Entry Conference Gate
          </span>
        </div>

        <div className="flex items-center justify-between">
          <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
            Execution Mode
          </span>
          <span
            className={`rounded px-1.5 py-0.2 text-[10px] font-bold border ${
              isWaiver
                ? "bg-amber-50 text-amber-700 border-amber-200"
                : "bg-emerald-50 text-emerald-700 border-emerald-200"
            }`}
          >
            {isWaiver ? "Authorized Waiver" : "Standard Conference"}
          </span>
        </div>
      </div>

      {/* Dual Path Switcher (MDS-200 §11 Rule 11) */}
      <div className="rounded-lg border border-slate-200 bg-slate-50/50 p-2 space-y-1.5 text-xs">
        <span className="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">
          Commencement Protocol Path
        </span>
        <div className="grid grid-cols-2 gap-1">
          <button
            type="button"
            onClick={() => onToggleWaiverMode(false)}
            className={`rounded px-2 py-1.5 text-[11px] font-semibold transition cursor-pointer text-center ${
              !isWaiver
                ? "bg-blue-600 text-white shadow-2xs"
                : "bg-white text-slate-700 hover:bg-slate-100 border border-slate-200"
            }`}
          >
            Conduct Conference
          </button>

          <button
            type="button"
            onClick={() => onToggleWaiverMode(true)}
            className={`rounded px-2 py-1.5 text-[11px] font-semibold transition cursor-pointer text-center ${
              isWaiver
                ? "bg-amber-600 text-white shadow-2xs"
                : "bg-white text-slate-700 hover:bg-slate-100 border border-slate-200"
            }`}
          >
            Authorized Waiver
          </button>
        </div>
      </div>

      {/* Readiness Checklist */}
      <div className="space-y-2">
        <div className="flex items-center justify-between text-xs">
          <span className="font-bold text-slate-800 flex items-center gap-1">
            <FileCheck2 size={12} className="text-blue-600" />
            <span>{isWaiver ? "Waiver Readiness Checklist" : "Conference Readiness Checklist"}</span>
          </span>
          <span
            className={`font-mono text-[10px] font-bold px-1.5 py-0.2 rounded ${
              passedCount === checks.length
                ? "bg-emerald-50 text-emerald-700 border border-emerald-200"
                : "bg-amber-50 text-amber-700 border border-amber-200"
            }`}
          >
            {passedCount}/{checks.length} Complete
          </span>
        </div>

        <div className="space-y-1.5 divide-y divide-slate-100 text-xs">
          {checks.map((chk, idx) => (
            <div key={idx} className="flex items-start justify-between gap-2 pt-1.5">
              <div className="flex items-start gap-1.5 min-w-0">
                {chk.valid ? (
                  <CheckCircle2 size={13} className="text-emerald-600 shrink-0 mt-0.5" />
                ) : (
                  <XCircle size={13} className="text-slate-300 shrink-0 mt-0.5" />
                )}
                <span
                  className={`text-[11px] leading-tight ${
                    chk.valid ? "text-slate-700 font-medium" : "text-slate-400"
                  }`}
                >
                  {chk.label}
                </span>
              </div>
              <span className="text-[10px] text-slate-400 shrink-0 font-mono">
                {chk.detail}
              </span>
            </div>
          ))}
        </div>
      </div>

      {/* Phase Gate Callout (§10.1, §11 Rule 11) */}
      {isReady && (
        <div className="rounded-lg border border-emerald-200 bg-emerald-50/80 p-2.5 space-y-1 text-xs text-emerald-950">
          <div className="flex items-center gap-1.5 font-bold text-emerald-900">
            <CheckCircle2 size={13} className="text-emerald-600 shrink-0" />
            <span>Phase 3 Milestone Satisfied</span>
          </div>
          <p className="text-[11px] text-emerald-800 leading-snug">
            All commencement prerequisites are met. The engagement is officially prepared to proceed to <strong>Audit Execution (Phase 4)</strong>.
          </p>
        </div>
      )}

      {/* Action Buttons */}
      <div className="space-y-2 pt-1">
        <Button
          type="button"
          onClick={onSubmitFinalize}
          disabled={!isReady}
          className="h-10 w-full rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold shadow-sm flex items-center justify-center gap-2 transition active:scale-[0.99] disabled:opacity-50"
        >
          <Send size={14} />
          <span>{isWaiver ? "Affirm Authorized Waiver" : "Document & Finalize Conference"}</span>
        </Button>

        <Button
          type="button"
          variant="outline"
          onClick={onSaveDraft}
          className="h-9 w-full rounded-lg border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold shadow-2xs flex items-center justify-center gap-1.5 transition active:scale-[0.99]"
        >
          <Save size={13} className="text-blue-600" />
          <span>Save Conference Draft</span>
        </Button>

        <Button
          type="button"
          variant="outline"
          onClick={() =>
            navigateTo(`/admin/audit-engagement/${formState.engagementNumber}/planning`)
          }
          className="h-9 w-full rounded-lg border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold shadow-2xs flex items-center justify-center gap-1.5 transition active:scale-[0.99]"
        >
          <ArrowLeft size={13} />
          <span>Return to Planning Workspace (SCR-221)</span>
        </Button>
      </div>
    </div>
  );
}
