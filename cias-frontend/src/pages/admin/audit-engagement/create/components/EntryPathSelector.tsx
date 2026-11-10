import { CalendarCheck, PlusCircle } from "lucide-react";
import type { EngagementOriginType } from "../create-engagement-types";
import { cn } from "../../../../../utils/helpers";

type EntryPathSelectorProps = {
  selectedPath: EngagementOriginType;
  onSelectPath: (path: EngagementOriginType) => void;
};

export function EntryPathSelector({
  selectedPath,
  onSelectPath,
}: EntryPathSelectorProps) {
  return (
    <div className="flex flex-col gap-2.5 rounded-lg border border-slate-200 bg-white p-3 shadow-sm sm:flex-row sm:items-center sm:justify-between">
      <div>
        <div className="flex items-center gap-1.5">
          <span className="text-[10px] font-bold uppercase tracking-wider text-blue-700 bg-blue-50 px-1.5 py-0.5 rounded border border-blue-200">
            Step 1
          </span>
          <span className="text-[13px] font-bold text-slate-800">
            Engagement Origin & Path
          </span>
        </div>
        <p className="text-[11px] text-slate-500 mt-0.5">
          Choose whether to activate an approved item from the Annual Internal Audit Plan (IAP) or create an unplanned engagement.
        </p>
      </div>

      {/* Segmented Toggle matching Dashboard */}
      <div className="flex items-center rounded-lg bg-slate-100 p-1 border border-slate-200/80 shrink-0">
        <button
          type="button"
          onClick={() => onSelectPath("Planned")}
          className={cn(
            "flex items-center gap-1.5 rounded-md px-2.5 py-1 text-[11px] font-semibold transition-all duration-150 cursor-pointer",
            selectedPath === "Planned"
              ? "bg-white text-blue-700 shadow-xs ring-1 ring-slate-200 font-bold"
              : "text-slate-600 hover:text-slate-900",
          )}
        >
          <CalendarCheck className="h-3.5 w-3.5 text-blue-600" />
          <span>Approved IAP (Planned)</span>
        </button>

        <button
          type="button"
          onClick={() => onSelectPath("Unplanned")}
          className={cn(
            "flex items-center gap-1.5 rounded-md px-2.5 py-1 text-[11px] font-semibold transition-all duration-150 cursor-pointer",
            selectedPath === "Unplanned"
              ? "bg-white text-blue-700 shadow-xs ring-1 ring-slate-200 font-bold"
              : "text-slate-600 hover:text-slate-900",
          )}
        >
          <PlusCircle className="h-3.5 w-3.5 text-amber-600" />
          <span>Unplanned Engagement</span>
        </button>
      </div>
    </div>
  );
}
