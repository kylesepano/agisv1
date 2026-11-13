import { Check, CircleDot, Clock } from "lucide-react";
import { phaseDefinitions } from "../engagement-details-data";
import { cn } from "../../../../../utils/helpers";

type PhaseStepperProps = {
  currentPhaseNumber: number;
};

export function PhaseStepper({ currentPhaseNumber }: PhaseStepperProps) {
  return (
    <div className="rounded-lg border border-slate-200 bg-white p-3 shadow-sm space-y-2">
      <div className="flex items-center justify-between border-b border-slate-100 pb-1.5">
        <div className="flex items-center gap-1.5">
          <Clock className="h-3.5 w-3.5 text-blue-600" />
          <span className="text-[12px] font-bold text-slate-800">
            AEM 10-Phase Lifecycle Stepper (MDS-200 §10.1)
          </span>
        </div>
        <span className="text-[10px] font-mono text-slate-400">
          Phase {currentPhaseNumber} of 10 Active
        </span>
      </div>

      {/* Horizontal Phase Sequence */}
      <div className="overflow-x-auto pb-1">
        <div className="flex items-center min-w-[760px] gap-1">
          {phaseDefinitions.map((p, idx) => {
            const isCompleted = p.number < currentPhaseNumber;
            const isCurrent = p.number === currentPhaseNumber;

            return (
              <div key={p.number} className="flex items-center flex-1 min-w-[68px]">
                <div
                  className={cn(
                    "flex flex-col items-center justify-center rounded-md p-1.5 w-full text-center transition select-none",
                    isCurrent
                      ? "bg-blue-600 text-white shadow-xs ring-1 ring-blue-600"
                      : isCompleted
                      ? "bg-emerald-50 border border-emerald-200 text-emerald-800"
                      : "bg-slate-50 border border-slate-200 text-slate-400",
                  )}
                  title={`${p.name}\n${p.description}`}
                >
                  <div className="flex items-center gap-1">
                    {isCompleted && <Check size={10} strokeWidth={3} className="text-emerald-600" />}
                    {isCurrent && <CircleDot size={10} className="text-white animate-pulse" />}
                    <span className="font-mono text-[9px] font-bold">
                      P{p.number}
                    </span>
                  </div>
                  <span
                    className={cn(
                      "text-[9px] font-semibold truncate max-w-[64px] mt-0.5",
                      isCurrent ? "text-white" : isCompleted ? "text-emerald-900" : "text-slate-500",
                    )}
                  >
                    {p.shortName}
                  </span>
                </div>

                {idx < phaseDefinitions.length - 1 && (
                  <div
                    className={cn(
                      "h-[2px] w-2 shrink-0 mx-0.5",
                      isCompleted ? "bg-emerald-400" : "bg-slate-200",
                    )}
                  />
                )}
              </div>
            );
          })}
        </div>
      </div>
    </div>
  );
}
