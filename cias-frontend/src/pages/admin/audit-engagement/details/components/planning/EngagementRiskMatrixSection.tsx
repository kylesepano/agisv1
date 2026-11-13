import {
  Info,
  Link,
  Scale,
} from "lucide-react";
import type { ControlEffectiveness, EngagementRiskItem, RiskRating } from "./planning-types";

type EngagementRiskMatrixSectionProps = {
  risks: EngagementRiskItem[];
};

export function EngagementRiskMatrixSection({
  risks,
}: EngagementRiskMatrixSectionProps) {
  const getRiskBadge = (rating: RiskRating) => {
    switch (rating) {
      case "Critical":
        return "bg-rose-50 text-rose-700 border-rose-200";
      case "High":
        return "bg-orange-50 text-orange-700 border-orange-200";
      case "Medium":
        return "bg-amber-50 text-amber-700 border-amber-200";
      default:
        return "bg-emerald-50 text-emerald-700 border-emerald-200";
    }
  };

  const getEffectivenessBadge = (eff: ControlEffectiveness) => {
    switch (eff) {
      case "Effective":
        return "bg-emerald-50 text-emerald-700 border-emerald-200";
      case "Partially Effective":
        return "bg-amber-50 text-amber-700 border-amber-200";
      default:
        return "bg-rose-50 text-rose-700 border-rose-200";
    }
  };

  return (
    <div className="rounded-lg border border-slate-200 bg-white p-3.5 shadow-sm space-y-3">
      {/* Section Header */}
      <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-2">
        <div className="flex items-center gap-2">
          <Scale className="h-4 w-4 text-blue-600" />
          <h3 className="text-[13px] font-bold text-slate-800">
            Engagement Risk Matrix (ERM) & Control Evaluation
          </h3>
          <span className="rounded bg-amber-50 px-1.5 py-0.2 text-[10px] font-bold text-amber-700 border border-amber-200">
            OD-003 Provisional
          </span>
          <span className="rounded bg-blue-50 px-1.5 py-0.2 text-[10px] font-bold text-blue-700 border border-blue-200">
            {risks.length} Risk Matrix Items
          </span>
        </div>
        <span className="text-[10px] text-slate-400 font-mono">
          MDS-200 §7, §9.2, §11 • Controlled Planning Artifact
        </span>
      </div>

      {/* OD-003 Provisional Notice Banner */}
      <div className="flex items-start gap-2 rounded-md border border-amber-200 bg-amber-50/70 p-2.5 text-xs text-amber-900">
        <Info size={14} className="text-amber-600 shrink-0 mt-0.5" />
        <div className="space-y-0.5 text-[11px] leading-snug">
          <span className="font-bold">
            Provisional Risk-Rating Model (MDS-200 v0.8 §19 OD-003):
          </span>{" "}
          Risk rating definitions, severity thresholds, and residual weighting matrices are provisionally captured pending formal determination of the standardized organizational risk model.
        </div>
      </div>

      {/* Controlled Risk Matrix Items */}
      {risks.length === 0 ? (
        <div className="rounded-lg border border-dashed border-slate-200 p-6 text-center space-y-1.5 bg-slate-50/50">
          <Scale className="h-6 w-6 text-slate-300 mx-auto" />
          <p className="text-xs font-semibold text-slate-700">No Engagement Risks Mapped</p>
          <p className="text-[11px] text-slate-400 max-w-md mx-auto">
            Document identified process risks, inherent and residual risk severities, and existing internal control effectiveness per MDS-200 §7.
          </p>
        </div>
      ) : (
        <div className="space-y-3">
          {risks.map((risk) => (
          <div
            key={risk.id}
            className="rounded-lg border border-slate-200 bg-slate-50/50 p-3 space-y-2.5 text-xs transition hover:bg-slate-50"
          >
            {/* Header: ID, Process Area, Risk Ratings */}
            <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200/80 pb-2">
              <div className="flex items-center gap-2">
                <span className="font-mono text-[10px] font-bold text-blue-700 bg-white px-2 py-0.5 rounded border border-blue-200">
                  {risk.id}
                </span>
                <span className="font-bold text-slate-800 text-[11px]">
                  {risk.processArea}
                </span>
              </div>

              {/* Dual Ratings: Inherent & Residual */}
              <div className="flex items-center gap-2">
                <div className="flex items-center gap-1">
                  <span className="text-[9px] text-slate-400 font-bold uppercase">
                    Inherent Risk:
                  </span>
                  <span
                    className={`rounded px-1.5 py-0.2 text-[9px] font-bold border ${getRiskBadge(
                      risk.inherentRisk,
                    )}`}
                  >
                    {risk.inherentRisk}
                  </span>
                </div>
                <div className="flex items-center gap-1">
                  <span className="text-[9px] text-slate-400 font-bold uppercase">
                    Residual Risk:
                  </span>
                  <span
                    className={`rounded px-1.5 py-0.2 text-[9px] font-bold border ${getRiskBadge(
                      risk.residualRisk,
                    )}`}
                  >
                    {risk.residualRisk}
                  </span>
                </div>
              </div>
            </div>

            {/* Risk Event Description */}
            <div className="space-y-0.5">
              <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">
                Potential Risk Event / Threat Description (MDS-200 §7)
              </span>
              <p className="text-[11px] font-medium text-slate-900 leading-snug">
                {risk.riskEvent}
              </p>
            </div>

            {/* 2-Column Evaluation: Controls & Audit Linkage */}
            <div className="grid grid-cols-1 gap-2.5 sm:grid-cols-2 text-[11px] pt-1">
              {/* Existing Controls */}
              <div className="rounded-md bg-white p-2.5 border border-slate-200/80 space-y-1">
                <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">
                  Existing Management Controls
                </span>
                <p className="text-slate-700 leading-snug">{risk.existingControls}</p>
                <div className="pt-1">
                  <span
                    className={`rounded px-1.5 py-0.2 text-[9px] font-bold border ${getEffectivenessBadge(
                      risk.controlEffectiveness,
                    )}`}
                  >
                    Effectiveness: {risk.controlEffectiveness}
                  </span>
                </div>
              </div>

              {/* Linked Audit Focus & Testing Procedure */}
              <div className="rounded-md bg-white p-2.5 border border-slate-200/80 space-y-1">
                <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">
                  Linked Audit Focus Area (SCR-212)
                </span>
                <p className="text-blue-900 font-semibold leading-snug">
                  {risk.auditFocusArea}
                </p>
                <div className="flex items-center gap-1.5 pt-1 text-slate-600 font-mono text-[10px]">
                  <Link size={11} className="text-blue-600 shrink-0" />
                  <span className="truncate">{risk.linkedProcedureRef}</span>
                </div>
              </div>
            </div>
          </div>
        ))}
      </div>
      )}
    </div>
  );
}
