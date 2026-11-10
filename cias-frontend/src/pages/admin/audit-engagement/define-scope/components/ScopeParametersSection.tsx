import { FileText, SlidersHorizontal } from "lucide-react";
import { cn } from "../../../../../utils/helpers";

type ScopeParametersSectionProps = {
  objectives: string;
  boundaries: string;
  exclusions: string;
  knownLimitations: string;
  objectivesError?: string;
  touchedObjectives?: boolean;
  isReadOnly?: boolean;
  onChangeObjectives: (val: string) => void;
  onChangeBoundaries: (val: string) => void;
  onChangeExclusions: (val: string) => void;
  onChangeLimitations: (val: string) => void;
  onBlurObjectives: () => void;
};

export function ScopeParametersSection({
  objectives,
  boundaries,
  exclusions,
  knownLimitations,
  objectivesError,
  touchedObjectives,
  isReadOnly = false,
  onChangeObjectives,
  onChangeBoundaries,
  onChangeExclusions,
  onChangeLimitations,
  onBlurObjectives,
}: ScopeParametersSectionProps) {
  return (
    <div className="space-y-3">
      {/* Engagement Objectives (Required, Engagement-level) */}
      <div className="rounded-lg border border-slate-200 bg-white p-3.5 shadow-sm space-y-3">
        <div className="flex items-center justify-between border-b border-slate-100 pb-2">
          <div className="flex items-center gap-1.5">
            <FileText className="h-4 w-4 text-blue-600" />
            <h3 className="text-[13px] font-bold text-slate-800">
              Audit Engagement Objectives
            </h3>
            <span className="text-rose-500 text-xs">*</span>
          </div>
          <span className="text-[10px] font-medium text-slate-400">
            Engagement-Level Mandate
          </span>
        </div>

        <div>
          <label className="block">
            <span className="text-xs font-semibold text-slate-700 mb-1.5 block">
              Engagement Objectives & Primary Purpose
            </span>
            <textarea
              rows={3}
              disabled={isReadOnly}
              className={cn(
                "w-full min-h-[96px] rounded-lg border bg-white p-2.5 text-xs text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-1 focus:ring-blue-500/20 leading-relaxed resize-y transition disabled:bg-slate-100 disabled:text-slate-500 disabled:cursor-not-allowed",
                touchedObjectives && objectivesError
                  ? "border-rose-400 focus:border-rose-500 bg-rose-50/20"
                  : "border-slate-200 focus:border-blue-500",
              )}
              placeholder="Formulate comprehensive audit objectives addressing regulatory compliance, control effectiveness, and financial integrity..."
              value={objectives}
              onChange={(e) => onChangeObjectives(e.target.value)}
              onBlur={onBlurObjectives}
            />
            {touchedObjectives && objectivesError && (
              <p className="mt-1 text-[11px] font-medium text-rose-600 animate-in fade-in-50">
                {objectivesError}
              </p>
            )}
          </label>
        </div>
      </div>

      {/* Scope Boundaries, Exclusions & Limitations */}
      <div className="rounded-lg border border-slate-200 bg-white p-3.5 shadow-sm space-y-3">
        <div className="flex items-center justify-between border-b border-slate-100 pb-2">
          <div className="flex items-center gap-1.5">
            <SlidersHorizontal className="h-4 w-4 text-blue-600" />
            <h3 className="text-[13px] font-bold text-slate-800">
              Scope Boundaries, Exclusions & Known Limitations
            </h3>
          </div>
          <span className="text-[10px] font-medium text-slate-400">
            MDS-200 §7 Formal Boundaries
          </span>
        </div>

        <div className="space-y-3">
          {/* Boundaries */}
          <div>
            <label className="block">
              <div className="flex items-center justify-between text-xs font-semibold text-slate-700 mb-1">
                <span>Boundaries & In-Scope Coverage</span>
                <span className="text-[10px] font-normal text-slate-400">Transaction periods, divisions, facilities</span>
              </div>
              <textarea
                rows={2}
                disabled={isReadOnly}
                className="w-full min-h-[80px] rounded-lg border border-slate-200 bg-white p-2.5 text-xs text-slate-900 placeholder:text-slate-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500/20 leading-relaxed resize-y transition disabled:bg-slate-100 disabled:text-slate-500 disabled:cursor-not-allowed"
                placeholder="Define geographical limits, operational divisions, transaction cutoff dates, and systems included in the review..."
                value={boundaries}
                onChange={(e) => onChangeBoundaries(e.target.value)}
              />
            </label>
          </div>

          {/* 2-Column Grid: Exclusions & Known Limitations */}
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            {/* Exclusions */}
            <div>
              <label className="block">
                <div className="flex items-center justify-between text-xs font-semibold text-slate-700 mb-1">
                  <span>Out-of-Scope Exclusions</span>
                  <span className="text-[10px] font-normal text-slate-400">Explicit exclusions</span>
                </div>
                <textarea
                  rows={2}
                  disabled={isReadOnly}
                  className="w-full min-h-[80px] rounded-lg border border-slate-200 bg-white p-2.5 text-xs text-slate-900 placeholder:text-slate-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500/20 leading-relaxed resize-y transition disabled:bg-slate-100 disabled:text-slate-500 disabled:cursor-not-allowed"
                  placeholder="Document specific excluded programs, external entity jurisdictions, or sub-processes..."
                  value={exclusions}
                  onChange={(e) => onChangeExclusions(e.target.value)}
                />
              </label>
            </div>

            {/* Known Limitations */}
            <div>
              <label className="block">
                <div className="flex items-center justify-between text-xs font-semibold text-slate-700 mb-1">
                  <span>Known Scope Limitations</span>
                  <span className="text-[10px] font-normal text-slate-400">Data/access constraints</span>
                </div>
                <textarea
                  rows={2}
                  disabled={isReadOnly}
                  className="w-full min-h-[80px] rounded-lg border border-slate-200 bg-white p-2.5 text-xs text-slate-900 placeholder:text-slate-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500/20 leading-relaxed resize-y transition disabled:bg-slate-100 disabled:text-slate-500 disabled:cursor-not-allowed"
                  placeholder="Note archival data gaps, pending system migrations, or staffing constraints affecting scope execution..."
                  value={knownLimitations}
                  onChange={(e) => onChangeLimitations(e.target.value)}
                />
              </label>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
