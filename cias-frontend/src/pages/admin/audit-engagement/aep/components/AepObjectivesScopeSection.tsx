import { Layers, Scale } from "lucide-react";
import { defaultCriteriaFrameworkOptions } from "../aep-data";

type AepObjectivesScopeSectionProps = {
  objectives: string;
  refinedScope: string;
  criteriaFramework: string[];
  onChangeObjectives: (val: string) => void;
  onChangeScope: (val: string) => void;
  onToggleCriteria: (criterion: string) => void;
};

export function AepObjectivesScopeSection({
  objectives,
  refinedScope,
  criteriaFramework,
  onChangeObjectives,
  onChangeScope,
  onToggleCriteria,
}: AepObjectivesScopeSectionProps) {
  return (
    <div className="rounded-lg border border-slate-200 bg-white p-3.5 shadow-sm space-y-3">
      <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-2">
        <div className="flex items-center gap-2">
          <Layers className="h-4 w-4 text-blue-600" />
          <h3 className="text-[13px] font-bold text-slate-800">
            Objectives, Refined Scope & Criteria Framework (§7)
          </h3>
        </div>
        <span className="text-[10px] text-slate-400 font-mono">
          Inherited & Refined from SCR-212
        </span>
      </div>

      <div className="space-y-3 text-xs">
        {/* Objectives */}
        <div className="space-y-1">
          <div className="flex items-center justify-between">
            <label className="text-[11px] font-semibold text-slate-700 block">
              Engagement Objectives (Planning Refinement)
            </label>
            <span className="text-[10px] text-slate-400">
              Pre-filled from SCR-212 baseline
            </span>
          </div>
          <textarea
            rows={3}
            value={objectives}
            onChange={(e) => onChangeObjectives(e.target.value)}
            placeholder="Define specific, verifiable audit objectives for this engagement..."
            className="w-full rounded border border-slate-200 bg-white p-2 text-xs text-slate-800 leading-relaxed focus:border-blue-500 focus:outline-hidden"
            required
          />
        </div>

        {/* Refined Scope */}
        <div className="space-y-1">
          <div className="flex items-center justify-between">
            <label className="text-[11px] font-semibold text-slate-700 block">
              Refined Scope & Operational Boundaries
            </label>
            <span className="text-[10px] text-slate-400">
              Coverage & transaction boundaries
            </span>
          </div>
          <textarea
            rows={3}
            value={refinedScope}
            onChange={(e) => onChangeScope(e.target.value)}
            placeholder="Document detailed transaction scope, sampling universe, and organizational units..."
            className="w-full rounded border border-slate-200 bg-white p-2 text-xs text-slate-800 leading-relaxed focus:border-blue-500 focus:outline-hidden"
            required
          />
        </div>

        {/* Criteria Framework (RA 9184, COA, etc.) */}
        <div className="space-y-2 pt-1 border-t border-slate-100">
          <div className="flex items-center justify-between">
            <label className="text-[11px] font-bold text-slate-800 flex items-center gap-1.5">
              <Scale size={13} className="text-blue-600" />
              <span>Criteria Framework / Applicable Standards (§7)</span>
            </label>
            <span className="text-[10px] font-bold text-blue-700 bg-blue-50 px-1.5 py-0.2 rounded border border-blue-200">
              {criteriaFramework.length} Selected
            </span>
          </div>
          <p className="text-[11px] text-slate-500 leading-snug">
            Select the statutory laws, COA circulars, executive orders, and auditing standards that govern this engagement:
          </p>

          <div className="grid grid-cols-1 gap-1.5 sm:grid-cols-2 rounded-lg border border-slate-200 bg-slate-50/60 p-2.5">
            {defaultCriteriaFrameworkOptions.map((opt, idx) => {
              const isChecked = criteriaFramework.includes(opt);
              return (
                <label
                  key={idx}
                  className={`flex items-start gap-2 rounded-md p-2 text-[11px] cursor-pointer transition border ${
                    isChecked
                      ? "border-blue-200 bg-white text-slate-900 shadow-2xs font-medium"
                      : "border-transparent bg-transparent text-slate-600 hover:bg-slate-100/80"
                  }`}
                >
                  <input
                    type="checkbox"
                    checked={isChecked}
                    onChange={() => onToggleCriteria(opt)}
                    className="mt-0.5 h-3.5 w-3.5 rounded border-slate-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                  />
                  <span className="leading-tight">{opt}</span>
                </label>
              );
            })}
          </div>
        </div>
      </div>
    </div>
  );
}
