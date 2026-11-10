import {
  AlertCircle,
  Shield,
  Workflow,
} from "lucide-react";
import type { ProcessFlowStep } from "./planning-types";

type ProcessFlowDocSectionProps = {
  flows: ProcessFlowStep[];
};

export function ProcessFlowDocSection({ flows }: ProcessFlowDocSectionProps) {
  return (
    <div className="rounded-lg border border-slate-200 bg-white p-3.5 shadow-sm space-y-3">
      <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-2">
        <div className="flex items-center gap-2">
          <Workflow className="h-4 w-4 text-blue-600" />
          <h3 className="text-[13px] font-bold text-slate-800">
            Process Flow Documentation & Control Points (PFD)
          </h3>
          <span className="rounded bg-blue-50 px-1.5 py-0.2 text-[10px] font-bold text-blue-700 border border-blue-200">
            {flows.length} Operational Stages Mapped
          </span>
        </div>
        <span className="text-[10px] text-slate-400 font-mono">
          MDS-200 §9.2, §11 • Controlled Planning Artifact
        </span>
      </div>

      {/* Sequence of Process Stages */}
      {flows.length === 0 ? (
        <div className="rounded-lg border border-dashed border-slate-200 p-6 text-center space-y-1.5 bg-slate-50/50">
          <Workflow className="h-6 w-6 text-slate-300 mx-auto" />
          <p className="text-xs font-semibold text-slate-700">No Process Flow Documentation Mapped</p>
          <p className="text-[11px] text-slate-400 max-w-md mx-auto">
            Document operational stages, key control points, and process risks for this engagement per MDS-200 §9.2.
          </p>
        </div>
      ) : (
        <div className="space-y-2.5">
          {flows.map((step) => (
          <div
            key={step.stepNumber}
            className="rounded-lg border border-slate-200 bg-slate-50/50 p-3 space-y-2 text-xs transition hover:bg-slate-50"
          >
            <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-1.5">
              <div className="flex items-center gap-2">
                <span className="grid h-6 w-6 shrink-0 place-items-center rounded bg-blue-600 text-[10px] font-bold text-white font-mono">
                  S{step.stepNumber}
                </span>
                <span className="font-bold text-slate-900 text-xs">{step.title}</span>
              </div>
              <span className="rounded bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600 border border-slate-200">
                {step.responsibleRole}
              </span>
            </div>

            <p className="text-[11px] text-slate-600 leading-relaxed">
              {step.description}
            </p>

            <div className="grid grid-cols-1 gap-2 sm:grid-cols-2 pt-1 border-t border-slate-100 text-[11px]">
              {/* Control Point */}
              <div className="rounded bg-emerald-50/60 p-2 border border-emerald-100 space-y-0.5">
                <span className="text-[10px] font-bold text-emerald-800 uppercase tracking-wider flex items-center gap-1">
                  <Shield size={11} className="text-emerald-600" />
                  Key Control Point
                </span>
                <p className="text-emerald-950 leading-snug">{step.controlPoint}</p>
              </div>

              {/* Identified Risks */}
              <div className="rounded bg-amber-50/60 p-2 border border-amber-100 space-y-1">
                <span className="text-[10px] font-bold text-amber-800 uppercase tracking-wider flex items-center gap-1">
                  <AlertCircle size={11} className="text-amber-600" />
                  Identified Process Risks
                </span>
                <div className="flex flex-wrap gap-1">
                  {step.identifiedRisks.map((risk, rIdx) => (
                    <span
                      key={rIdx}
                      className="rounded bg-white px-1.5 py-0.2 text-[9px] font-semibold text-amber-900 border border-amber-200"
                    >
                      {risk}
                    </span>
                  ))}
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
