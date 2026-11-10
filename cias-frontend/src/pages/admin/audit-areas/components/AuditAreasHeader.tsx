import { Database, Download, Layers, Plus } from "lucide-react";
import { Button } from "../../../../components/ui/button";
import { Heading, Paragraph } from "../../../../components/ui/typography";

type AuditAreasHeaderProps = {
  onAddArea?: () => void;
  onResetDefaults?: () => void;
};

export function AuditAreasHeader({ onAddArea, onResetDefaults }: AuditAreasHeaderProps) {
  return (
    <div className="flex items-start justify-between gap-3 max-[960px]:flex-col border-b border-slate-200 pb-2.5">
      {/* Title & Screen Badge */}
      <div className="flex items-start gap-2.5">
        <div className="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-blue-50 text-blue-600 border border-blue-100">
          <Layers className="h-4.5 w-4.5" />
        </div>
        <div>
          <div className="flex items-center gap-1.5">
            <span className="font-mono text-[10px] font-bold text-blue-700 bg-blue-50 px-1.5 py-0.2 rounded border border-blue-200">
              SCR-202
            </span>
            <Heading as={2} className="text-[1.1rem] font-bold tracking-tight text-slate-900 leading-snug">
              Audit Area Registry (Governed Audit Universe)
            </Heading>
          </div>
          <Paragraph className="mt-0.5 text-[11px] text-slate-500">
            Define and govern audit areas linked to parent auditee offices under MDS-000 §6 / Rule 7 (Office → Audit Area).
          </Paragraph>
        </div>
      </div>

      {/* Action Buttons */}
      <div className="flex flex-wrap items-center gap-2 max-[760px]:w-full">
        <Button
          className="h-10 rounded-lg bg-blue-600 hover:bg-blue-700 px-3.5 text-xs font-semibold text-white shadow-sm flex items-center gap-2 transition active:scale-[0.99] cursor-pointer"
          onClick={onAddArea}
        >
          <Plus className="h-4 w-4" />
          <span>Add Audit Area</span>
        </Button>

        {onResetDefaults && (
          <Button
            className="h-10 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 px-3 text-xs font-semibold text-slate-700 flex items-center gap-1.5 transition active:scale-[0.99] cursor-pointer"
            variant="outline"
            type="button"
            onClick={onResetDefaults}
            title="Restore master seed dataset"
          >
            <Database className="h-3.5 w-3.5 text-emerald-600" />
            <span>Reset to Seed Data</span>
          </Button>
        )}

        <Button
          className="h-10 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 px-3.5 text-xs font-semibold text-slate-700 flex items-center gap-2 transition active:scale-[0.99] cursor-pointer"
          variant="outline"
          type="button"
        >
          <Download className="h-4 w-4" />
          <span>Export</span>
        </Button>
      </div>
    </div>
  );
}
