import { Check, Layers } from "lucide-react";
import { localStore } from "../../../../../lib/localStore";
import { cn } from "../../../../../utils/helpers";

type AuditAreaSelectorProps = {
  selectedOffice: string;
  selectedAreaIds: string[];
  coverageNotes: Record<string, string>;
  error?: string;
  isReadOnly?: boolean;
  onToggleArea: (areaId: string) => void;
  onChangeCoverageNote: (areaId: string, note: string) => void;
};

export function AuditAreaSelector({
  selectedOffice,
  selectedAreaIds,
  coverageNotes,
  error,
  isReadOnly = false,
  onToggleArea,
  onChangeCoverageNote,
}: AuditAreaSelectorProps) {
  // If no office is selected yet, render disabled awaiting-office state per MDS-000 Rule 7
  if (!selectedOffice || selectedOffice.trim().length === 0) {
    return (
      <div className="rounded-lg border border-dashed border-slate-300 bg-slate-50/70 p-6 shadow-sm text-center space-y-2">
        <div className="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-slate-200/70 text-slate-500">
          <Layers className="h-5 w-5" />
        </div>
        <h4 className="text-xs font-bold text-slate-800">
          Audit Area Selection Awaiting Auditee Office
        </h4>
        <p className="text-[11px] text-slate-500 max-w-md mx-auto leading-relaxed">
          Please select a <strong>Target Auditee Office</strong> above to view and select applicable Audit Areas. Per <strong>MDS-000 Rule 7</strong>, every Audit Area must belong to the designated office.
        </p>
        {error && (
          <p className="mt-2 text-[11px] font-medium text-rose-600 animate-in fade-in-50">
            {error}
          </p>
        )}
      </div>
    );
  }

  // Live read from Audit Area Registry
  const allAreas = localStore.getAuditAreas();

  // Filter active areas strictly mapped to the selected office
  const officeAreas = allAreas.filter(
    (a) =>
      a.status === "Active" &&
      ((a.officeName || "").toLowerCase().trim() === selectedOffice.toLowerCase().trim() ||
        (a.officeId || "").toLowerCase().trim() === selectedOffice.toLowerCase().trim()),
  );

  return (
    <div className="rounded-lg border border-slate-200 bg-white p-3.5 shadow-sm space-y-3">
      <div className="flex items-center justify-between border-b border-slate-100 pb-2">
        <div className="flex items-center gap-1.5">
          <Layers className="h-4 w-4 text-blue-600" />
          <h3 className="text-[13px] font-bold text-slate-800">
            Audit Area(s) & Coverage Notes
          </h3>
          <span className="text-rose-500 text-xs">*</span>
        </div>
        <span className="text-[10px] font-medium text-slate-400">
          Multi-select (Minimum 1 required)
        </span>
      </div>

      <div>
        <div className="flex items-center justify-between mb-1.5">
          <span className="text-xs font-semibold text-slate-700">
            Select applicable Audit Areas for <strong className="text-slate-900">"{selectedOffice}"</strong>
          </span>
          <span className="text-[10px] text-slate-400">
            {officeAreas.length} {officeAreas.length === 1 ? "area" : "areas"} available
          </span>
        </div>

        {officeAreas.length === 0 ? (
          <div className="rounded-lg border border-amber-200 bg-amber-50/60 p-4 text-center">
            <p className="text-xs font-bold text-amber-800">
              No Configured Audit Areas
            </p>
            <p className="mt-0.5 text-[11px] text-amber-700">
              No audit areas are currently registered for <strong>{selectedOffice}</strong> in the master registry.
            </p>
          </div>
        ) : (
          /* Audit Area Cards Grid */
          <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
            {officeAreas.map((area) => {
              const areaKey = area.auditAreaId || area.code || area.id || "";
              const isSelected = selectedAreaIds.some(
                (id) =>
                  id.toLowerCase() === areaKey.toLowerCase() ||
                  id.toLowerCase() === (area.id || "").toLowerCase() ||
                  id.toLowerCase() === (area.code || "").toLowerCase() ||
                  id.toLowerCase() === (area.auditAreaId || "").toLowerCase(),
              );

              return (
                <div
                  key={areaKey}
                  onClick={() => !isReadOnly && onToggleArea(areaKey)}
                  className={cn(
                    "flex items-start gap-2.5 rounded-lg border p-2.5 transition-all duration-150 text-left select-none",
                    isReadOnly ? "cursor-default" : "cursor-pointer",
                    isSelected
                      ? "border-blue-500 bg-blue-50/40 shadow-xs ring-1 ring-blue-500/20"
                      : isReadOnly
                        ? "border-slate-200 bg-slate-100/50 opacity-60"
                        : "border-slate-200 bg-slate-50/50 hover:bg-slate-50 hover:border-slate-300",
                  )}
                >
                  <div
                    className={cn(
                      "grid h-4.5 w-4.5 shrink-0 place-items-center rounded mt-0.5 transition",
                      isSelected
                        ? "bg-blue-600 text-white"
                        : "border border-slate-300 bg-white text-transparent",
                    )}
                  >
                    <Check size={11} strokeWidth={3} />
                  </div>

                  <div className="min-w-0 flex-1">
                    <div className="flex items-center justify-between gap-1">
                      <span className="font-mono text-[10px] font-bold text-blue-700">
                        {area.auditAreaId || area.code}
                      </span>
                      <span className="rounded bg-white px-1.5 py-0.2 text-[9px] font-semibold text-slate-500 border border-slate-200">
                        {area.auditAreaCategory || area.category}
                      </span>
                    </div>

                    <h4 className="mt-0.5 text-xs font-bold text-slate-900 leading-snug">
                      {area.auditAreaName || area.name}
                    </h4>

                    <p className="mt-0.5 text-[11px] text-slate-500 line-clamp-2 leading-relaxed">
                      {area.description}
                    </p>
                  </div>
                </div>
              );
            })}
          </div>
        )}

        {error && (
          <p className="mt-1.5 text-[11px] font-medium text-rose-600 animate-in fade-in-50">
            {error}
          </p>
        )}
      </div>

      {/* Per-Audit-Area Coverage Notes Section */}
      {selectedAreaIds.length > 0 && (
        <div className="space-y-2.5 pt-2 border-t border-slate-100">
          <div className="flex items-center justify-between">
            <span className="text-xs font-bold text-slate-800">
              Per-Area Coverage & Scope Notes
            </span>
            <span className="text-[10px] text-slate-400">
              {selectedAreaIds.length} {selectedAreaIds.length === 1 ? "area" : "areas"} selected
            </span>
          </div>

          <div className="space-y-2">
            {selectedAreaIds.map((areaId) => {
              const area = allAreas.find(
                (a) =>
                  a.auditAreaId?.toLowerCase() === areaId.toLowerCase() ||
                  a.code?.toLowerCase() === areaId.toLowerCase() ||
                  a.id?.toLowerCase() === areaId.toLowerCase(),
              );
              if (!area) return null;

              const areaKey = area.auditAreaId || area.code || area.id || areaId;
              const areaTitle = area.auditAreaName || area.name || areaId;
              const areaCode = area.auditAreaId || area.code || areaId;

              return (
                <div
                  key={areaId}
                  className="rounded-lg border border-slate-200 bg-slate-50/70 p-2.5 space-y-1.5"
                >
                  <div className="flex items-center justify-between">
                    <div className="flex items-center gap-1.5">
                      <span className="font-mono text-[10px] font-bold text-blue-700 bg-blue-50 px-1.5 py-0.2 rounded border border-blue-200">
                        {areaCode}
                      </span>
                      <span className="text-xs font-bold text-slate-800">
                        {areaTitle}
                      </span>
                    </div>
                    <span className="text-[10px] text-slate-400">Coverage Description</span>
                  </div>

                  <textarea
                    rows={2}
                    disabled={isReadOnly}
                    className="w-full rounded-md border border-slate-200 bg-white p-2 text-xs text-slate-900 placeholder:text-slate-400 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500/20 transition resize-y disabled:bg-slate-100 disabled:text-slate-500 disabled:cursor-not-allowed"
                    placeholder={`Specify specific transaction scopes, sample periods, or system modules for ${areaTitle}...`}
                    value={coverageNotes[areaId] || coverageNotes[areaKey] || ""}
                    onChange={(e) => onChangeCoverageNote(areaId, e.target.value)}
                  />
                </div>
              );
            })}
          </div>
        </div>
      )}
    </div>
  );
}
