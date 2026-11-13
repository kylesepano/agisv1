import { Check, Compass } from "lucide-react";
import { localStore } from "../../../../../lib/localStore";
import { cn } from "../../../../../utils/helpers";

type AuditFocusSelectorProps = {
  selectedAreaIds: string[];
  selectedFocusIds: string[];
  isReadOnly?: boolean;
  onToggleFocus: (focusId: string) => void;
};

export function AuditFocusSelector({
  selectedAreaIds,
  selectedFocusIds,
  isReadOnly = false,
  onToggleFocus,
}: AuditFocusSelectorProps) {
  // If no areas selected, show gentle placeholder
  if (selectedAreaIds.length === 0) {
    return (
      <div className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm text-center">
        <Compass className="h-6 w-6 text-slate-300 mx-auto" />
        <p className="mt-1.5 text-xs font-semibold text-slate-700">Audit Focuses</p>
        <p className="mt-0.5 text-[11px] text-slate-400">
          Select at least one Audit Area above to configure granular audit focus items.
        </p>
      </div>
    );
  }

  // Live reads from master registries (Single Source of Truth)
  const allAreas = localStore.getAuditAreas();
  const allFocuses = localStore.getAuditFocuses();

  return (
    <div className="rounded-lg border border-slate-200 bg-white p-3.5 shadow-sm space-y-3">
      <div className="flex items-center justify-between border-b border-slate-100 pb-2">
        <div className="flex items-center gap-1.5">
          <Compass className="h-4 w-4 text-blue-600" />
          <h3 className="text-[13px] font-bold text-slate-800">
            Applicable Audit Focuses
          </h3>
        </div>
        <span className="text-[10px] font-medium text-slate-400">
          Grouped by parent Audit Area (Optional / Recommended)
        </span>
      </div>

      <p className="text-[11px] text-slate-500">
        Tag specific control sub-areas, risk points, or transaction cycles within the selected audit boundaries.
      </p>

      {/* Grouped Focus Cards by Area */}
      <div className="space-y-3">
        {selectedAreaIds.map((areaId) => {
          const area = allAreas.find(
            (a) =>
              a.auditAreaId?.toLowerCase() === areaId.toLowerCase() ||
              a.code?.toLowerCase() === areaId.toLowerCase() ||
              a.id?.toLowerCase() === areaId.toLowerCase(),
          );

          // Match active focuses by parent auditAreaId or aliases
          const targetAreaKeys = [
            areaId.toLowerCase(),
            (area?.auditAreaId || "").toLowerCase(),
            (area?.code || "").toLowerCase(),
            (area?.id || "").toLowerCase(),
          ].filter(Boolean);

          const areaFocuses = allFocuses.filter(
            (f) =>
              f.status === "Active" &&
              targetAreaKeys.includes((f.auditAreaId || "").toLowerCase()),
          );

          const areaCodeDisplay = area?.auditAreaId || area?.code || areaId;
          const areaNameDisplay = area?.auditAreaName || area?.name || areaId;

          return (
            <div
              key={areaId}
              className="rounded-lg border border-slate-200 bg-slate-50/50 p-2.5 space-y-2"
            >
              <div className="flex items-center justify-between border-b border-slate-200/60 pb-1.5">
                <div className="flex items-center gap-1.5">
                  <span className="font-mono text-[10px] font-bold text-blue-700 bg-white px-1.5 py-0.2 rounded border border-slate-200">
                    {areaCodeDisplay}
                  </span>
                  <span className="text-xs font-bold text-slate-800">
                    {areaNameDisplay}
                  </span>
                </div>
                <span className="text-[10px] text-slate-500 font-medium">
                  {areaFocuses.length} {areaFocuses.length === 1 ? "focus item" : "focus items"}
                </span>
              </div>

              {areaFocuses.length === 0 ? (
                <div className="rounded-lg border border-dashed border-slate-200 bg-white/70 p-3 text-center">
                  <p className="text-[11px] font-semibold text-slate-600">
                    No Audit Focuses available for this Area
                  </p>
                  <p className="mt-0.5 text-[10px] text-slate-400">
                    Granular audit focus items can be defined in the Audit Focus Registry (SCR-203).
                  </p>
                </div>
              ) : (
                <div className="grid grid-cols-1 gap-1.5 sm:grid-cols-2">
                  {areaFocuses.map((focus) => {
                    const focusKey = focus.auditFocusId || focus.code || focus.id || "";
                    const isSelected = selectedFocusIds.some(
                      (id) =>
                        id.toLowerCase() === focusKey.toLowerCase() ||
                        id.toLowerCase() === (focus.id || "").toLowerCase() ||
                        id.toLowerCase() === (focus.code || "").toLowerCase() ||
                        id.toLowerCase() === (focus.auditFocusId || "").toLowerCase(),
                    );

                    return (
                      <div
                        key={focusKey}
                        onClick={() => !isReadOnly && onToggleFocus(focusKey)}
                        className={cn(
                          "flex items-start gap-2 rounded-md border p-2 text-left transition select-none",
                          isReadOnly ? "cursor-default" : "cursor-pointer",
                          isSelected
                            ? "border-blue-500 bg-white shadow-2xs ring-1 ring-blue-500/20"
                            : isReadOnly
                              ? "border-slate-200 bg-slate-50 opacity-60"
                              : "border-slate-200 bg-white hover:border-slate-300",
                        )}
                      >
                        <div
                          className={cn(
                            "grid h-4 w-4 shrink-0 place-items-center rounded mt-0.5 transition",
                            isSelected
                              ? "bg-blue-600 text-white"
                              : "border border-slate-300 bg-white text-transparent",
                          )}
                        >
                          <Check size={10} strokeWidth={3} />
                        </div>

                        <div className="min-w-0 flex-1">
                          <div className="flex items-center justify-between gap-1">
                            <span className="font-mono text-[9px] font-bold text-slate-500">
                              {focus.auditFocusId || focus.code}
                            </span>
                          </div>
                          <h5 className="text-[11px] font-bold text-slate-800 leading-snug">
                            {focus.auditFocusName || focus.name}
                          </h5>
                          <p className="mt-0.5 text-[10px] text-slate-500 line-clamp-2 leading-tight">
                            {focus.description}
                          </p>
                        </div>
                      </div>
                    );
                  })}
                </div>
              )}
            </div>
          );
        })}
      </div>
    </div>
  );
}
