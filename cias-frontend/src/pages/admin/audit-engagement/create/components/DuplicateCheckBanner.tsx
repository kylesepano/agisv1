import { AlertTriangle } from "lucide-react";
import type { DuplicateConflict } from "../create-engagement-types";

type DuplicateCheckBannerProps = {
  conflicts: DuplicateConflict[];
};

// GAP-001 (docs/mds-200-gaps.md): MDS-200 v0.8 mentions duplicate controls in SCR-211 purpose
// description but specifies no formal Module Rule. Duplicate check & SCR-214 override review
// are implemented as an approved House Convention / Implementation Rule.
export function DuplicateCheckBanner({ conflicts }: DuplicateCheckBannerProps) {
  if (conflicts.length === 0) {
    return null;
  }

  return (
    <div className="rounded-xl border border-amber-300/80 bg-amber-50/70 p-3.5 shadow-2xs">
      <div className="flex items-start gap-2.5">
        <div className="grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-amber-100 text-amber-800 border border-amber-200">
          <AlertTriangle className="h-4 w-4" />
        </div>
        <div className="flex-1 min-w-0">
          <div className="flex flex-wrap items-center justify-between gap-1.5">
            <h4 className="text-xs font-bold text-amber-900 flex items-center gap-1.5">
              <span>Potential Engagement Duplicate Detected</span>
              <span className="rounded-full bg-amber-200/80 px-1.5 py-0.2 text-[10px] font-semibold text-amber-900">
                {conflicts.length} Overlap
              </span>
            </h4>
            <div className="flex items-center gap-1.5">
              <span className="text-[9px] font-semibold text-slate-600 bg-slate-100 px-1.5 py-0.5 rounded border border-slate-200">
                Implementation Rule
              </span>
              <span className="text-[10px] font-semibold text-amber-700 bg-amber-100/90 px-2 py-0.5 rounded border border-amber-200">
                Non-Blocking Notice
              </span>
            </div>
          </div>

          <p className="mt-0.5 text-[11px] text-amber-800 leading-normal">
            Matching <strong>Office</strong>, <strong>Audit Type</strong>, and overlapping dates detected. Creation is permitted; authorization override will be reviewed in SCR-214.
          </p>

          <div className="mt-2 space-y-1.5">
            {conflicts.map((conflict) => (
              <div
                key={conflict.engagementId}
                className="flex flex-col gap-1 rounded-lg border border-amber-200/80 bg-white/95 px-2.5 py-1.5 text-[11px] sm:flex-row sm:items-center sm:justify-between"
              >
                <div className="flex items-center gap-1.5 truncate">
                  <span className="font-mono font-bold text-blue-700">
                    {conflict.code}
                  </span>
                  <span className="text-slate-300">•</span>
                  <span className="font-medium text-slate-800 truncate">
                    {conflict.title}
                  </span>
                </div>
                <div className="flex flex-wrap items-center gap-1.5 text-[10px] text-slate-500 shrink-0">
                  <span className="rounded bg-slate-100 px-1.5 py-0.5 font-medium text-slate-700">
                    {conflict.office}
                  </span>
                  <span className="font-mono text-slate-500">
                    {conflict.startDate} ~ {conflict.endDate}
                  </span>
                  <span className="rounded bg-amber-100 px-1.5 py-0.2 font-semibold text-amber-800">
                    {conflict.status}
                  </span>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
}
