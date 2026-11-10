import { CheckCircle2, FileSpreadsheet, ShieldAlert } from "lucide-react";
import { approvedIapItems } from "../create-engagement-data";
import type { IapSourceItem } from "../create-engagement-types";
import { Select2 } from "../../../../../components/ui/select2";

type IapSourceSelectorProps = {
  selectedIapId: string;
  onSelectIapItem: (item: IapSourceItem) => void;
  iapItems?: IapSourceItem[];
  error?: string;
  isDuplicate?: boolean;
  duplicateMessage?: string;
};

export function IapSourceSelector({
  selectedIapId,
  onSelectIapItem,
  iapItems,
  error,
  isDuplicate = false,
  duplicateMessage,
}: IapSourceSelectorProps) {
  const availableItems = iapItems && iapItems.length > 0 ? iapItems : approvedIapItems;

  const selectedItem =
    availableItems.find((item) => item.id === selectedIapId) || null;

  const iapSelectOptions = availableItems.map((item) => ({
    value: item.id,
    label: `${item.code} — ${item.title}`,
  }));

  return (
    <div className="rounded-lg border border-slate-200 bg-white p-3 shadow-sm space-y-2.5">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-1.5">
          <FileSpreadsheet className="h-3.5 w-3.5 text-emerald-600" />
          <span className="text-[13px] font-bold text-slate-800">
            Select Approved IAP Item
          </span>
          <span className="text-rose-500 text-xs">*</span>
        </div>
        <div className="flex items-center gap-1.5">
          <span className="text-[10px] text-emerald-700 font-semibold bg-emerald-50 px-1.5 py-0.2 rounded border border-emerald-200">
            {availableItems.length} items in registry
          </span>
        </div>
      </div>

      <Select2
        value={selectedIapId}
        options={iapSelectOptions}
        placeholder="Search and select approved annual plan item..."
        searchPlaceholder="Type plan code, subject, or office..."
        onChange={(val) => {
          const item = availableItems.find((i) => i.id === val);
          if (item) {
            onSelectIapItem(item);
          }
        }}
      />

      {error && (
        <p className="text-[11px] font-medium text-rose-600 animate-in fade-in-50">
          {error}
        </p>
      )}

      {/* Selected Item Preview Bar with Duplicate Guard Indicator */}
      {selectedItem && (
        <div className="space-y-1.5">
          <div className="flex flex-wrap items-center justify-between gap-2 rounded-md border border-emerald-200/90 bg-emerald-50/50 px-2.5 py-1.5 text-[11px]">
            <div className="flex items-center gap-2 flex-wrap">
              <span className="font-mono font-bold text-emerald-950">
                {selectedItem.code}
              </span>
              <span className="rounded bg-emerald-100 px-1.5 py-0.2 text-[10px] font-bold text-emerald-800">
                {selectedItem.year} {selectedItem.quarter}
              </span>
              <span className="text-slate-600">
                {selectedItem.auditType}
              </span>
            </div>

            <div className="flex items-center gap-2">
              <span className="font-mono text-[10px] text-slate-500">
                {selectedItem.periodStart} ~ {selectedItem.periodEnd}
              </span>
              <span className="rounded bg-white px-1.5 py-0.5 text-[9px] font-bold text-emerald-700 border border-emerald-200 shadow-2xs">
                Pre-filled
              </span>
            </div>
          </div>

          {/* Lightweight SCR-211 Duplicate Check Control */}
          <div className="flex items-center justify-between px-1 text-[10px]">
            {isDuplicate ? (
              <div className="flex items-center gap-1.5 text-amber-700 font-medium">
                <ShieldAlert size={12} className="text-amber-600" />
                <span>{duplicateMessage || "Warning: This IAP Item has already been activated in another engagement."}</span>
              </div>
            ) : (
              <div className="flex items-center gap-1 text-emerald-700 font-medium">
                <CheckCircle2 size={12} className="text-emerald-600" />
                <span>Duplicate Control: Eligible for activation (No prior active engagement linked)</span>
              </div>
            )}
            <span className="text-[9px] font-semibold text-slate-500 bg-slate-100 px-1.5 py-0.5 rounded border border-slate-200">
              Implementation Rule
            </span>
          </div>
        </div>
      )}
    </div>
  );
}
