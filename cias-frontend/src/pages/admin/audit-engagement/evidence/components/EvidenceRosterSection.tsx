import {
  ArrowRight,
  Binary,
  CheckCircle2,
  FolderLock,
  Plus,
  Search,
} from "lucide-react";
import { useState } from "react";
import { Button } from "../../../../../components/ui/button";
import type { AuditEvidenceItem, EvidenceStatus } from "../evidence-types";

type EvidenceRosterSectionProps = {
  evidenceItems: AuditEvidenceItem[];
  activeEvidenceId: string;
  onSelectEvidence: (id: string) => void;
  onCreateNewEvidence: () => void;
};

export function EvidenceRosterSection({
  evidenceItems,
  activeEvidenceId,
  onSelectEvidence,
  onCreateNewEvidence,
}: EvidenceRosterSectionProps) {
  const [searchTerm, setSearchTerm] = useState("");
  const [statusFilter, setStatusFilter] = useState<string>("All");

  const filteredItems = evidenceItems.filter((item) => {
    const term = searchTerm.toLowerCase();
    const matchesSearch =
      item.id.toLowerCase().includes(term) ||
      item.title.toLowerCase().includes(term) ||
      item.custodian.toLowerCase().includes(term) ||
      item.fileReference.toLowerCase().includes(term) ||
      item.source.toLowerCase().includes(term);

    const matchesStatus =
      statusFilter === "All" || item.status === statusFilter;

    return matchesSearch && matchesStatus;
  });

  const getStatusBadgeClass = (status: EvidenceStatus) => {
    switch (status) {
      case "Accepted":
        return "bg-emerald-50 text-emerald-700 border-emerald-300";
      case "Accepted with Limitation":
        return "bg-amber-50 text-amber-800 border-amber-300";
      case "For Assessment":
        return "bg-blue-50 text-blue-700 border-blue-300";
      case "Registered":
        return "bg-slate-100 text-slate-700 border-slate-300";
      case "Additional Evidence Required":
        return "bg-purple-50 text-purple-700 border-purple-300";
      case "Rejected":
        return "bg-rose-50 text-rose-700 border-rose-300";
      case "Superseded":
        return "bg-orange-50 text-orange-700 border-orange-300";
      case "Duplicate":
        return "bg-zinc-100 text-zinc-600 border-zinc-300";
      default:
        return "bg-slate-100 text-slate-700 border-slate-200";
    }
  };

  return (
    <div className="rounded-lg border border-slate-200 bg-white p-3.5 shadow-sm space-y-3">
      {/* Header & Filter Controls */}
      <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between border-b border-slate-100 pb-2.5">
        <div className="flex items-center gap-2">
          <FolderLock className="h-4 w-4 text-emerald-700" />
          <div>
            <h3 className="text-[13px] font-bold text-slate-800">
              Engagement Audit Evidence Repository (MDS-200 §9.3 & §10.6 Vault)
            </h3>
            <p className="text-[11px] text-slate-500">
              Master cryptographic evidence register, chain of custody logs, and evaluative status repository.
            </p>
          </div>
        </div>

        <div className="flex flex-wrap items-center gap-2 shrink-0">
          <div className="relative">
            <Search
              size={12}
              className="absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400"
            />
            <input
              type="text"
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
              placeholder="Search ID, title, custodian..."
              className="h-8 w-44 rounded-md border border-slate-200 bg-white pl-7 pr-2.5 text-xs text-slate-800 placeholder:text-slate-400 focus:border-emerald-500 focus:outline-hidden"
            />
          </div>

          <select
            value={statusFilter}
            onChange={(e) => setStatusFilter(e.target.value)}
            className="h-8 rounded-md border border-slate-200 bg-white px-2 text-xs text-slate-700 focus:border-emerald-500 focus:outline-hidden"
          >
            <option value="All">All Statuses</option>
            <option value="Accepted">Accepted</option>
            <option value="Accepted with Limitation">Accepted w/ Limitation</option>
            <option value="For Assessment">For Assessment</option>
            <option value="Registered">Registered</option>
            <option value="Additional Evidence Required">Additional Required</option>
            <option value="Rejected">Rejected</option>
          </select>

          <Button
            type="button"
            onClick={onCreateNewEvidence}
            className="h-8 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold px-2.5 flex items-center gap-1 cursor-pointer"
          >
            <Plus size={12} />
            <span>New Evidence Item</span>
          </Button>
        </div>
      </div>

      {/* Roster Cards List */}
      <div className="space-y-2">
        {filteredItems.length === 0 ? (
          <div className="rounded border border-dashed border-slate-200 bg-slate-50/50 p-6 text-center text-xs text-slate-400">
            No evidence records matched your search filter.
          </div>
        ) : (
          filteredItems.map((item) => {
            const isActive = item.id === activeEvidenceId;

            return (
              <div
                key={item.id}
                onClick={() => onSelectEvidence(item.id)}
                className={`rounded-lg border p-3 transition cursor-pointer space-y-2 ${
                  isActive
                    ? "border-emerald-400 bg-emerald-50/40 shadow-xs ring-1 ring-emerald-300"
                    : "border-slate-200 bg-white hover:border-emerald-200 hover:bg-slate-50/70"
                }`}
              >
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <div className="flex items-center gap-2 min-w-0">
                    <span className="font-mono text-xs font-bold text-emerald-800 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200 shrink-0">
                      {item.id}
                    </span>
                    <span className="text-xs font-bold text-slate-900 truncate max-w-sm">
                      {item.title}
                    </span>
                  </div>

                  <div className="flex items-center gap-1.5 shrink-0">
                    <span className="rounded bg-slate-100 px-2 py-0.5 text-[10px] font-mono text-slate-600">
                      {item.evidenceType}
                    </span>
                    <span
                      className={`rounded px-2 py-0.5 text-[10px] font-bold border ${getStatusBadgeClass(
                        item.status,
                      )}`}
                    >
                      {item.status}
                    </span>
                    {isActive ? (
                      <span className="inline-flex items-center gap-1 font-mono text-[10px] font-bold text-emerald-800 bg-white px-2 py-0.5 rounded border border-emerald-300">
                        <CheckCircle2 size={11} />
                        <span>Active</span>
                      </span>
                    ) : (
                      <span className="inline-flex items-center gap-0.5 text-[11px] font-medium text-slate-500 hover:text-emerald-700">
                        <span>Select</span>
                        <ArrowRight size={11} />
                      </span>
                    )}
                  </div>
                </div>

                <div className="grid grid-cols-1 gap-2 text-[11px] text-slate-500 sm:grid-cols-3 pt-1 border-t border-slate-100">
                  <div className="truncate">
                    <strong className="text-slate-700 font-medium">Custodian:</strong>{" "}
                    {item.custodian}
                  </div>
                  <div className="flex items-center gap-1 font-mono text-[10px] text-slate-600 truncate">
                    <Binary size={10} className="text-emerald-600 shrink-0" />
                    <span>{item.hashChecksum.substring(0, 16)}...</span>
                  </div>
                  <div className="flex items-center justify-between sm:justify-end gap-2">
                    <span className="text-[10px] text-emerald-800 bg-emerald-50 px-1.5 py-0.2 rounded font-mono font-bold">
                      {item.custodyLog.length} Custody Log{item.custodyLog.length === 1 ? "" : "s"}
                    </span>
                    <span className="text-[10px] text-slate-400 font-mono">
                      Received: {item.dateReceived}
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
