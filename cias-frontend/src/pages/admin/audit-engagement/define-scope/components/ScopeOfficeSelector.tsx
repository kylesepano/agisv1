import { useMemo } from "react";
import { AlertTriangle, Building2, CheckCircle2, RotateCcw } from "lucide-react";
import { localStore } from "../../../../../lib/localStore";
import type { OfficeRecord } from "../../../offices/offices-types";
import { Select2 } from "../../../../../components/ui/select2";
import { cn } from "../../../../../utils/helpers";

type ScopeOfficeSelectorProps = {
  selectedOffice: string;
  iapOffice: string;
  originType: "Planned" | "Unplanned";
  isOfficeChangedFromIap: boolean;
  sourceVarianceReason: string;
  error?: string;
  varianceError?: string;
  touched?: boolean;
  isReadOnly?: boolean;
  onChangeOffice: (office: string) => void;
  onChangeVarianceReason: (reason: string) => void;
  onResetToIapOffice: () => void;
};

export function ScopeOfficeSelector({
  selectedOffice,
  iapOffice,
  originType,
  isOfficeChangedFromIap,
  sourceVarianceReason,
  error,
  varianceError,
  touched,
  isReadOnly = false,
  onChangeOffice,
  onChangeVarianceReason,
  onResetToIapOffice,
}: ScopeOfficeSelectorProps) {
  // Read active offices from the single source of truth in localStore
  const officeOptions = useMemo(() => {
    const offices = localStore.getOffices();
    return offices
      .filter((o: OfficeRecord) => o.status === "Active")
      .map((o: OfficeRecord) => {
        const name = o.officeName || o.name || "";
        const acronym = o.officeAcronym || o.shortName || "";
        return {
          value: name,
          label: acronym ? `${name} (${acronym})` : name,
        };
      });
  }, []);

  return (
    <div className="rounded-lg border border-slate-200 bg-white p-3.5 shadow-sm space-y-3">
      <div className="flex items-center justify-between border-b border-slate-100 pb-2">
        <div className="flex items-center gap-1.5">
          <Building2 className="h-4 w-4 text-blue-600" />
          <h3 className="text-[13px] font-bold text-slate-800">
            Target Auditee Office
          </h3>
          <span className="text-rose-500 text-xs">*</span>
        </div>
        <span className="text-[10px] font-medium text-slate-400">
          MDS-200 §7 Entity Field
        </span>
      </div>

      <div className="space-y-2">
        <div className="block">
          <div className="flex items-center justify-between text-xs font-semibold text-slate-700 mb-1.5">
            <span>Select Single Auditee Office / Department</span>
            {originType === "Planned" && Boolean(iapOffice?.trim()) && (
              <span className="text-[10px] font-normal text-slate-400">
                Source IAP: <strong className="text-slate-600 font-semibold">{iapOffice}</strong>
              </span>
            )}
          </div>

          <Select2
            value={selectedOffice}
            options={officeOptions}
            placeholder="Search and select auditee office..."
            searchPlaceholder="Type office name..."
            disabled={isReadOnly}
            onChange={onChangeOffice}
          />

          {touched && error && (
            <p className="mt-1 text-[11px] font-medium text-rose-600 animate-in fade-in-50">
              {error}
            </p>
          )}
        </div>

        {/* Source IAP Reference & Variance Indicator (Planned Only) */}
        {originType === "Planned" && Boolean(iapOffice?.trim()) && (
          <div className="pt-1">
            {!isOfficeChangedFromIap ? (
              <div className="flex items-center justify-between rounded-md border border-emerald-200 bg-emerald-50/60 px-2.5 py-1.5 text-[11px]">
                <div className="flex items-center gap-1.5 text-emerald-800 font-medium">
                  <CheckCircle2 size={13} className="text-emerald-600 shrink-0" />
                  <span>Matches approved IAP annual plan auditee ({iapOffice})</span>
                </div>
                <span className="text-[10px] text-emerald-700 font-mono">No variance</span>
              </div>
            ) : (
              <div className="space-y-2 rounded-lg border border-amber-300 bg-amber-50/70 p-3 text-xs">
                <div className="flex items-start justify-between gap-2">
                  <div className="flex items-center gap-1.5 text-amber-900 font-bold">
                    <AlertTriangle size={14} className="text-amber-600 shrink-0 mt-0.5" />
                    <span>Authorized Source Variance Detected</span>
                  </div>
                  {!isReadOnly && (
                    <button
                      type="button"
                      onClick={onResetToIapOffice}
                      className="flex items-center gap-1 text-[10px] font-semibold text-blue-700 hover:text-blue-800 underline shrink-0 cursor-pointer"
                    >
                      <RotateCcw size={11} />
                      <span>Reset to IAP ({iapOffice})</span>
                    </button>
                  )}
                </div>

                <p className="text-[11px] text-amber-800/90 leading-snug">
                  The selected Office (<strong>{selectedOffice}</strong>) differs from the approved Annual Plan source (<strong>{iapOffice}</strong>). Per MDS-200 §7, you must document the authorized variance justification below.
                </p>

                <div>
                  <label className="block">
                    <span className="text-[11px] font-bold text-amber-900 mb-1 block">
                      Authorized Source Variance Justification <span className="text-rose-500">*</span>
                    </span>
                    <textarea
                      rows={2}
                      disabled={isReadOnly}
                      className={cn(
                        "w-full rounded-md border bg-white p-2 text-xs text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-1 transition resize-y disabled:bg-slate-100 disabled:text-slate-500 disabled:cursor-not-allowed",
                        varianceError
                          ? "border-rose-400 focus:border-rose-500 bg-rose-50/20"
                          : "border-amber-300 focus:border-blue-500",
                      )}
                      placeholder="Specify executive authorization, council resolution, or reorganization rationale for this office modification..."
                      value={sourceVarianceReason}
                      onChange={(e) => onChangeVarianceReason(e.target.value)}
                    />
                    {varianceError && (
                      <p className="mt-1 text-[11px] font-medium text-rose-600">
                        {varianceError}
                      </p>
                    )}
                  </label>
                </div>
              </div>
            )}
          </div>
        )}
      </div>
    </div>
  );
}
