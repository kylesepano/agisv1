import {
  FileText,
  ListOrdered,
  Plus,
} from "lucide-react";
import { Button } from "../../../../../components/ui/button";
import type { FieldworkRecord } from "../fieldwork-types";

type FieldworkRecordsListSectionProps = {
  records: FieldworkRecord[];
  activeRecordId: string;
  onSelectRecord: (id: string) => void;
  onCreateNewRecord: () => void;
};

export function FieldworkRecordsListSection({
  records,
  activeRecordId,
  onSelectRecord,
  onCreateNewRecord,
}: FieldworkRecordsListSectionProps) {
  return (
    <div className="rounded-lg border border-slate-200 bg-white p-3.5 shadow-sm space-y-3">
      <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-2">
        <div className="flex items-center gap-2">
          <ListOrdered className="h-4 w-4 text-blue-600" />
          <h3 className="text-[13px] font-bold text-slate-800">
            Engagement Fieldwork Records Roster ({records.length})
          </h3>
        </div>

        <Button
          type="button"
          variant="outline"
          onClick={onCreateNewRecord}
          className="h-7 text-xs px-2.5 flex items-center gap-1 text-blue-600 hover:text-blue-700 cursor-pointer"
        >
          <Plus size={12} />
          <span>New Fieldwork Record</span>
        </Button>
      </div>

      <div className="space-y-1.5">
        {records.map((r) => {
          const isActive = r.id === activeRecordId;
          const isReviewed = r.status === "Reviewed";
          const isSubmitted = r.status === "Submitted";

          return (
            <div
              key={r.id}
              onClick={() => onSelectRecord(r.id)}
              className={`rounded-lg border p-2.5 transition cursor-pointer flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 ${
                isActive
                  ? "border-blue-500 bg-blue-50/50 shadow-2xs"
                  : "border-slate-200 bg-white hover:bg-slate-50/80"
              }`}
            >
              <div className="flex items-start gap-2.5 min-w-0">
                <div
                  className={`grid h-8 w-8 shrink-0 place-items-center rounded-md font-mono text-xs font-bold ${
                    isActive
                      ? "bg-blue-600 text-white"
                      : "bg-slate-100 text-slate-700"
                  }`}
                >
                  <FileText size={15} />
                </div>

                <div className="space-y-0.5 min-w-0">
                  <div className="flex flex-wrap items-center gap-1.5">
                    <span className="font-mono text-xs font-bold text-blue-700">
                      {r.id}
                    </span>
                    <span className="rounded bg-indigo-50 px-1.5 py-0.2 text-[10px] font-bold text-indigo-700 border border-indigo-200">
                      {r.type}
                    </span>
                    <span className="font-mono text-[10px] text-slate-400">
                      {r.date}
                    </span>
                  </div>

                  <p className="text-xs font-semibold text-slate-900 truncate">
                    {r.procedureId}: {r.procedureTitle}
                  </p>

                  <span className="text-[10px] text-slate-500 block truncate">
                    Location: {r.location} • Prep: {r.preparer}
                  </span>
                </div>
              </div>

              <div className="flex items-center gap-2 shrink-0 self-end sm:self-center">
                <span
                  className={`rounded px-1.5 py-0.2 text-[10px] font-bold border ${
                    isReviewed
                      ? "bg-emerald-50 text-emerald-700 border-emerald-200"
                      : isSubmitted
                      ? "bg-blue-50 text-blue-700 border-blue-200"
                      : "bg-slate-100 text-slate-600 border-slate-200"
                  }`}
                >
                  {r.status}
                </span>

                {isActive && (
                  <span className="rounded bg-blue-600 text-white text-[9px] font-bold px-1.5 py-0.2">
                    Active Editor
                  </span>
                )}
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
}
