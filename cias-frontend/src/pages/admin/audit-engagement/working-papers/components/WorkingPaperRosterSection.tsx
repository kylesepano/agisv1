import {
  ArrowRight,
  CheckCircle2,
  FileSpreadsheet,
  Plus,
  Search,
} from "lucide-react";
import { useState } from "react";
import { Button } from "../../../../../components/ui/button";
import type { WorkingPaper } from "../working-papers-types";

type WorkingPaperRosterSectionProps = {
  workingPapers: WorkingPaper[];
  activeWorkingPaperId: string;
  onSelectWorkingPaper: (id: string) => void;
  onCreateNewWorkingPaper: () => void;
};

export function WorkingPaperRosterSection({
  workingPapers,
  activeWorkingPaperId,
  onSelectWorkingPaper,
  onCreateNewWorkingPaper,
}: WorkingPaperRosterSectionProps) {
  const [searchTerm, setSearchTerm] = useState("");

  const filteredWps = workingPapers.filter((wp) => {
    const term = searchTerm.toLowerCase();
    return (
      wp.id.toLowerCase().includes(term) ||
      wp.referenceNumber.toLowerCase().includes(term) ||
      wp.procedureId.toLowerCase().includes(term) ||
      wp.procedureTitle.toLowerCase().includes(term) ||
      wp.purpose.toLowerCase().includes(term) ||
      wp.preparer.toLowerCase().includes(term)
    );
  });

  const getStatusBadgeClass = (status: WorkingPaper["reviewStatus"]) => {
    switch (status) {
      case "Cleared":
        return "bg-emerald-50 text-emerald-700 border-emerald-200";
      case "Reviewed":
        return "bg-blue-50 text-blue-700 border-blue-200";
      case "Submitted":
        return "bg-amber-50 text-amber-800 border-amber-200";
      case "Draft":
      default:
        return "bg-slate-100 text-slate-700 border-slate-200";
    }
  };

  return (
    <div className="rounded-lg border border-slate-200 bg-white p-3.5 shadow-sm space-y-3">
      {/* Header with Search and New Button */}
      <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between border-b border-slate-100 pb-2.5">
        <div className="flex items-center gap-2">
          <FileSpreadsheet className="h-4 w-4 text-purple-700" />
          <div>
            <h3 className="text-[13px] font-bold text-slate-800">
              Engagement Working Papers Roster (MDS-200 §9.3 Repository)
            </h3>
            <p className="text-[11px] text-slate-500">
              Master index of audit execution testing schedules, substantive workpapers, and reconciliations.
            </p>
          </div>
        </div>

        <div className="flex items-center gap-2 shrink-0">
          <div className="relative">
            <Search
              size={12}
              className="absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400"
            />
            <input
              type="text"
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
              placeholder="Search WP ID, procedure..."
              className="h-8 w-44 rounded-md border border-slate-200 bg-white pl-7 pr-2.5 text-xs text-slate-800 placeholder:text-slate-400 focus:border-purple-500 focus:outline-hidden"
            />
          </div>

          <Button
            type="button"
            onClick={onCreateNewWorkingPaper}
            className="h-8 rounded-md bg-purple-600 hover:bg-purple-700 text-white text-xs font-semibold px-2.5 flex items-center gap-1 cursor-pointer"
          >
            <Plus size={12} />
            <span>New Working Paper</span>
          </Button>
        </div>
      </div>

      {/* Roster Cards Table/List */}
      <div className="space-y-2">
        {filteredWps.length === 0 ? (
          <div className="rounded border border-dashed border-slate-200 bg-slate-50/50 p-6 text-center text-xs text-slate-400">
            No working papers matched your search criteria.
          </div>
        ) : (
          filteredWps.map((wp) => {
            const isActive = wp.id === activeWorkingPaperId;

            return (
              <div
                key={wp.id}
                onClick={() => onSelectWorkingPaper(wp.id)}
                className={`rounded-lg border p-3 transition cursor-pointer space-y-2 ${
                  isActive
                    ? "border-purple-400 bg-purple-50/40 shadow-xs ring-1 ring-purple-300"
                    : "border-slate-200 bg-white hover:border-purple-200 hover:bg-slate-50/70"
                }`}
              >
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <div className="flex items-center gap-2 min-w-0">
                    <span className="font-mono text-xs font-bold text-purple-700 bg-purple-50 px-2 py-0.5 rounded border border-purple-200 shrink-0">
                      {wp.id}
                    </span>
                    <span className="text-[11px] font-mono text-slate-500 hidden sm:inline">
                      ({wp.referenceNumber})
                    </span>
                    <span className="text-xs font-bold text-slate-800 truncate max-w-sm">
                      {wp.procedureTitle}
                    </span>
                  </div>

                  <div className="flex items-center gap-1.5 shrink-0">
                    <span className="rounded bg-slate-100 px-2 py-0.5 text-[10px] font-mono text-slate-600">
                      {wp.type}
                    </span>
                    <span
                      className={`rounded px-2 py-0.5 text-[10px] font-bold border ${getStatusBadgeClass(
                        wp.reviewStatus,
                      )}`}
                    >
                      {wp.reviewStatus}
                    </span>
                    {isActive ? (
                      <span className="inline-flex items-center gap-1 font-mono text-[10px] font-bold text-purple-700 bg-white px-2 py-0.5 rounded border border-purple-300">
                        <CheckCircle2 size={11} />
                        <span>Active</span>
                      </span>
                    ) : (
                      <span className="inline-flex items-center gap-0.5 text-[11px] font-medium text-slate-500 hover:text-purple-700">
                        <span>Select</span>
                        <ArrowRight size={11} />
                      </span>
                    )}
                  </div>
                </div>

                <div className="grid grid-cols-1 gap-2 text-[11px] text-slate-500 sm:grid-cols-3 pt-1 border-t border-slate-100">
                  <div className="truncate">
                    <strong className="text-slate-700 font-medium">Procedure:</strong>{" "}
                    <span className="font-mono text-indigo-700 font-semibold">{wp.procedureId}</span>
                  </div>
                  <div className="truncate">
                    <strong className="text-slate-700 font-medium">Preparer:</strong> {wp.preparer}
                  </div>
                  <div className="flex items-center justify-between sm:justify-end gap-2">
                    <span className="text-[10px] text-purple-800 bg-purple-50 px-1.5 py-0.2 rounded font-mono font-bold">
                      {wp.evidenceLinks.length} Evidence Ref{wp.evidenceLinks.length === 1 ? "" : "s"}
                    </span>
                    <span className="text-[10px] text-slate-400 font-mono">
                      Updated: {wp.updatedAt}
                    </span>
                  </div>
                </div>
              </div>
            );
          })
        )}
      </div>
    </div>
  );
}
