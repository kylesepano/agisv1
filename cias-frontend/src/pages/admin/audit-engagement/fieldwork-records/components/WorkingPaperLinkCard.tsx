import { ExternalLink, FileSpreadsheet } from "lucide-react";
import { navigateTo } from "../../../../../routes";

type WorkingPaperLinkCardProps = {
  engagementNumber: string;
  workingPaperRef: string;
  procedureId: string;
};

export function WorkingPaperLinkCard({
  engagementNumber,
  workingPaperRef,
  procedureId,
}: WorkingPaperLinkCardProps) {
  return (
    <div className="rounded-lg border border-purple-200 bg-purple-50/60 p-3 shadow-2xs space-y-2">
      <div className="flex flex-wrap items-center justify-between gap-2 border-b border-purple-200/80 pb-1.5">
        <div className="flex items-center gap-2">
          <FileSpreadsheet className="h-4 w-4 text-purple-700" />
          <h4 className="text-xs font-bold text-purple-950">
            Source Material Linkage (MDS-200 §11 Rule 13 Mandate)
          </h4>
        </div>
        <span className="font-mono text-[10px] font-bold text-purple-800 bg-white px-1.5 py-0.2 rounded border border-purple-300">
          Rule 13 Invariant
        </span>
      </div>

      <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between text-xs">
        <div className="space-y-0.5 max-w-xl">
          <p className="text-[11px] text-purple-900 leading-snug">
            <strong>Rule 13 Principle:</strong> A Fieldwork Record is factual source evidence, not a replacement for an indexed Working Paper. This record feeds directly into <strong>{workingPaperRef}</strong> under procedure <strong>{procedureId}</strong>.
          </p>
        </div>

        <button
          type="button"
          onClick={() =>
            navigateTo(
              `/admin/audit-engagement/${engagementNumber}/execution/workpapers/${workingPaperRef}`,
            )
          }
          className="inline-flex items-center gap-1.5 rounded-md bg-white px-3 py-1.5 text-xs font-semibold text-purple-800 border border-purple-300 hover:bg-purple-100/60 transition shadow-2xs cursor-pointer shrink-0"
        >
          <span>Open Working Paper (SCR-228)</span>
          <ExternalLink size={12} />
        </button>
      </div>
    </div>
  );
}
