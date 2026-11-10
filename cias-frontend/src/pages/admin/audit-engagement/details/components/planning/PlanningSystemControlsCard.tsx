import {
  AlertCircle,
  Calendar,
  CheckCircle2,
  ExternalLink,
  FileCheck2,
  Save,
  Send,
  Shield,
  XCircle,
} from "lucide-react";
import { Button } from "../../../../../../components/ui/button";
import { navigateTo } from "../../../../../../routes";
import type { PlanningPackageState } from "./planning-types";

type PlanningSystemControlsCardProps = {
  packageState: PlanningPackageState;
  engagementNumber?: string;
  onSaveDraft: () => void;
  onSubmitReview: () => void;
  onScheduleEntryConf: () => void;
};

export function PlanningSystemControlsCard({
  packageState,
  engagementNumber = "AEMS-2026-010",
  onSaveDraft,
  onSubmitReview,
  onScheduleEntryConf,
}: PlanningSystemControlsCardProps) {
  const isConfConducted = !!packageState.entryConference?.isConductedOrWaived;

  const checks = [
    {
      label: "Process Flow Documentation mapped",
      valid: packageState.processFlows.length >= 3,
      isProvisional: false,
      detail: `${packageState.processFlows.length} stages`,
      link: undefined,
    },
    {
      label: "Engagement Risk Matrix (OD-003 Provisional)",
      valid: packageState.risks.length >= 3,
      isProvisional: true,
      detail: `${packageState.risks.length} risks (Provisional)`,
      link: undefined,
    },
    {
      label: "Audit Engagement Plan drafted (SCR-222)",
      valid: (packageState.aep.auditStrategySummary || "").length >= 20,
      isProvisional: false,
      detail: "View Plan →",
      link: `/admin/audit-engagement/${engagementNumber}/planning/aep`,
    },
    {
      label: "Audit Programs & Procedures ready (SCR-223)",
      valid: packageState.auditPrograms.length >= 2,
      isProvisional: false,
      detail: "View Programs →",
      link: `/admin/audit-engagement/${engagementNumber}/planning/programs`,
    },
    {
      label: "Entry Conference conducted (SCR-225)",
      valid: isConfConducted,
      isProvisional: false,
      detail: isConfConducted
        ? `${packageState.entryConference?.date || "Feb 18, 2026"} • ${packageState.entryConference?.status || "Documented"}`
        : "Schedule →",
      link: `/admin/audit-engagement/${engagementNumber}/conference/entry`,
    },
    {
      label: "Supervising Auditor review assigned",
      valid: (packageState.reviewer || "").length > 0,
      isProvisional: false,
      detail: "Assigned",
      link: undefined,
    },
  ];

  const confirmedCount = checks.filter((c) => c.valid && !c.isProvisional).length;
  const provisionalCount = checks.filter((c) => c.isProvisional).length;

  return (
    <div className="rounded-lg border border-slate-200 bg-white p-3.5 shadow-sm space-y-3.5">
      <div className="flex items-center justify-between border-b border-slate-100 pb-2">
        <div className="flex items-center gap-1.5 font-bold text-slate-800 text-xs">
          <Shield size={14} className="text-blue-600" />
          <span>Planning Package Controls (§7, §11)</span>
        </div>
        <span className="rounded bg-blue-50 px-1.5 py-0.2 text-[10px] font-mono font-bold text-blue-700">
          SCR-221
        </span>
      </div>

      {/* Package Governance Identity */}
      <div className="rounded-lg border border-slate-100 bg-slate-50/70 p-2.5 space-y-1.5 text-xs">
        <div className="flex items-center justify-between">
          <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
            Package Lifecycle
          </span>
          <span className="rounded bg-indigo-50 px-1.5 py-0.2 text-[10px] font-bold text-indigo-700 border border-indigo-200">
            Phase 2 of 10 • Audit Planning
          </span>
        </div>

        <div className="flex items-center justify-between">
          <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
            Package Status
          </span>
          <span className="rounded bg-amber-50 px-1.5 py-0.2 text-[10px] font-bold text-amber-700 border border-amber-200">
            {packageState.status} ({packageState.version})
          </span>
        </div>

        <div className="flex items-center justify-between">
          <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
            Reviewing Supervisor
          </span>
          <span className="font-semibold text-slate-700 text-[11px] truncate max-w-[170px]">
            {packageState.reviewer}
          </span>
        </div>
      </div>

      {/* Planning Readiness Checklist */}
      <div className="space-y-2">
        <div className="flex items-center justify-between text-xs">
          <span className="font-bold text-slate-800 flex items-center gap-1">
            <FileCheck2 size={12} className="text-blue-600" />
            <span>Planning Readiness Checklist</span>
          </span>
          <span className="font-mono text-[10px] font-bold px-1.5 py-0.2 rounded bg-amber-50 text-amber-800 border border-amber-200">
            {confirmedCount}/6 Confirmed • {provisionalCount} Provisional
          </span>
        </div>

        <div className="space-y-1.5 divide-y divide-slate-100 text-xs">
          {checks.map((chk, idx) => (
            <div key={idx} className="flex items-start justify-between gap-2 pt-1.5">
              <div className="flex items-start gap-1.5 min-w-0">
                {chk.isProvisional ? (
                  <AlertCircle size={13} className="text-amber-600 shrink-0 mt-0.5" />
                ) : chk.valid ? (
                  <CheckCircle2 size={13} className="text-emerald-600 shrink-0 mt-0.5" />
                ) : (
                  <XCircle size={13} className="text-slate-300 shrink-0 mt-0.5" />
                )}
                {chk.link ? (
                  <button
                    type="button"
                    onClick={() => chk.link && navigateTo(chk.link)}
                    className="text-[11px] leading-tight text-blue-600 hover:text-blue-800 hover:underline font-semibold text-left cursor-pointer flex items-center gap-1"
                  >
                    <span>{chk.label}</span>
                    <ExternalLink size={10} className="shrink-0" />
                  </button>
                ) : (
                  <span
                    className={`text-[11px] leading-tight ${
                      chk.isProvisional
                        ? "text-amber-900 font-semibold"
                        : chk.valid
                        ? "text-slate-700 font-medium"
                        : "text-slate-400"
                    }`}
                  >
                    {chk.label}
                  </span>
                )}
              </div>
              {chk.link ? (
                <button
                  type="button"
                  onClick={() => chk.link && navigateTo(chk.link)}
                  className="text-[10px] shrink-0 font-mono text-blue-600 hover:underline font-bold cursor-pointer"
                >
                  {chk.detail}
                </button>
              ) : (
                <span
                  className={`text-[10px] shrink-0 font-mono ${
                    chk.isProvisional ? "text-amber-700 font-bold" : "text-slate-400"
                  }`}
                >
                  {chk.detail}
                </span>
              )}
            </div>
          ))}
        </div>
      </div>

      {/* Action Buttons */}
      <div className="space-y-2 pt-1">
        <Button
          type="button"
          onClick={onSubmitReview}
          disabled={
            packageState.status === "Under Review" ||
            packageState.processFlows.length < 3 ||
            packageState.risks.length < 2
          }
          className="h-10 w-full rounded-lg bg-blue-600 hover:bg-blue-700 disabled:bg-slate-200 disabled:text-slate-400 disabled:cursor-not-allowed text-white text-xs font-semibold shadow-sm flex items-center justify-center gap-2 transition active:scale-[0.99] cursor-pointer"
        >
          <Send size={14} />
          <span>
            {packageState.status === "Under Review"
              ? "Package Under Review"
              : "Submit Package for Review"}
          </span>
        </Button>

        <Button
          type="button"
          variant="outline"
          onClick={onSaveDraft}
          className="h-9 w-full rounded-lg border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold shadow-2xs flex items-center justify-center gap-1.5 transition active:scale-[0.99]"
        >
          <Save size={13} className="text-blue-600" />
          <span>Save Planning Draft</span>
        </Button>

        {isConfConducted ? (
          <Button
            type="button"
            variant="outline"
            onClick={() =>
              navigateTo(
                `/admin/audit-engagement/${engagementNumber}/conference/entry`,
              )
            }
            className="h-9 w-full rounded-lg border-emerald-200 bg-emerald-50/40 hover:bg-emerald-100/60 text-emerald-900 text-xs font-semibold shadow-2xs flex items-center justify-between px-2.5 transition active:scale-[0.99]"
          >
            <div className="flex items-center gap-1.5">
              <CheckCircle2 size={13} className="text-emerald-600 shrink-0" />
              <span>Entry Conference Conducted (SCR-225)</span>
            </div>
            <span className="text-[10px] font-mono font-bold text-emerald-700 bg-white px-1.5 py-0.2 rounded border border-emerald-200">
              {packageState.entryConference?.status || "Documented"}
            </span>
          </Button>
        ) : (
          <Button
            type="button"
            variant="outline"
            onClick={onScheduleEntryConf}
            className="h-9 w-full rounded-lg border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold shadow-2xs flex items-center justify-center gap-1.5 transition active:scale-[0.99]"
          >
            <Calendar size={13} className="text-indigo-600" />
            <span>Schedule Entry Conference (Phase 3)</span>
          </Button>
        )}
      </div>
    </div>
  );
}
