import {
  AlertTriangle,
  ArrowRight,
  CalendarDays,
  Eye,
  FileSearch,
  Flag,
  Info,
  X,
} from "lucide-react";
import { useState } from "react";
import { createPortal } from "react-dom";
import { Button } from "../../../../components/ui/button";
import { useToast } from "../../../../components/ui/toast";
import { navigateTo } from "../../../../routes";

type AfrModalProps = {
  isOpen: boolean;
  onClose: () => void;
};

const findingsData = [
  {
    findingNo: "FND-2025-009-01",
    finding: "Incomplete procurement documents",
    engagement: "AEMS-2025-009",
    office: "CHUDD",
    riskLevel: "High",
    riskBadge: "bg-orange-100 text-orange-700 border-orange-200",
    status: "Reported",
    statusBadge: "bg-purple-100 text-purple-700 border-purple-200",
    dateIssued: "May 18, 2025",
  },
  {
    findingNo: "FND-2025-008-01",
    finding: "Delayed accomplishment reports",
    engagement: "AEMS-2025-008",
    office: "City Engineer's Office",
    riskLevel: "Medium",
    riskBadge: "bg-amber-100 text-amber-700 border-amber-200",
    status: "Validated",
    statusBadge: "bg-blue-100 text-blue-700 border-blue-200",
    dateIssued: "May 17, 2025",
  },
  {
    findingNo: "FND-2025-007-01",
    finding: "Unsupported permit adjustments",
    engagement: "AEMS-2025-007",
    office: "BPLD",
    riskLevel: "Critical",
    riskBadge: "bg-rose-100 text-rose-700 border-rose-200",
    status: "Reported",
    statusBadge: "bg-purple-100 text-purple-700 border-purple-200",
    dateIssued: "May 15, 2025",
  },
  {
    findingNo: "FND-2025-006-01",
    finding: "Incomplete security procedures",
    engagement: "AEMS-2025-006",
    office: "CIO",
    riskLevel: "High",
    riskBadge: "bg-orange-100 text-orange-700 border-orange-200",
    status: "Draft",
    statusBadge: "bg-slate-100 text-slate-700 border-slate-200",
    dateIssued: "May 14, 2025",
  },
  {
    findingNo: "FND-2025-005-01",
    finding: "Delayed bank reconciliation",
    engagement: "AEMS-2025-005",
    office: "City Treasurer's Office",
    riskLevel: "Medium",
    riskBadge: "bg-amber-100 text-amber-700 border-amber-200",
    status: "Closed",
    statusBadge: "bg-emerald-100 text-emerald-700 border-emerald-200",
    dateIssued: "May 12, 2025",
  },
];

export function AfrModal({ isOpen, onClose }: AfrModalProps) {
  const { showToast } = useToast();
  const [activeTab, setActiveTab] = useState<"findings" | "recommendations">(
    "findings",
  );

  if (!isOpen || typeof document === "undefined") {
    return null;
  }

  const handleRowClick = (findingNo: string) => {
    showToast({
      title: `Finding ${findingNo}`,
      description:
        "Opening detailed finding sheet preview for audit working paper verification.",
      tone: "info",
    });
  };

  const handleViewAll = () => {
    showToast({
      title: "Audit Finding & Recommendation Registry",
      description:
        "Navigating to the complete AFR master registry. Module preview enabled.",
      tone: "info",
    });
    onClose();
    navigateTo("/admin/reports");
  };

  return createPortal(
    <div
      aria-modal="true"
      role="dialog"
      className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 p-4 backdrop-blur-[2px]"
      onClick={onClose}
    >
      <div
        className="relative flex max-h-[calc(100vh-2rem)] w-full max-w-[1040px] flex-col overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-[0_24px_80px_rgba(15,23,42,0.25)] animate-[page-enter_200ms_cubic-bezier(0.22,1,0.36,1)]"
        onClick={(e) => e.stopPropagation()}
      >
        {/* Header */}
        <div className="flex items-start justify-between border-b border-slate-100 px-7 pb-5 pt-6">
          <div className="flex items-center gap-4">
            <div className="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-orange-50 text-orange-600 border border-orange-100 shadow-sm">
              <FileSearch size={26} strokeWidth={2} />
            </div>
            <div>
              <h2 className="text-xl font-bold tracking-tight text-slate-900">
                Audit Finding &amp; Recommendation (AFR)
              </h2>
              <p className="mt-0.5 text-sm text-slate-500 font-normal">
                Overview of audit findings and recommendations across all engagements.
              </p>
            </div>
          </div>

          <button
            aria-label="Close modal"
            className="grid h-9 w-9 place-items-center rounded-xl text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
            type="button"
            onClick={onClose}
          >
            <X size={20} />
          </button>
        </div>

        {/* Modal Scrollable Body */}
        <div className="flex-1 overflow-y-auto px-7 py-6">
          {/* Top 4 Stat Cards */}
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {/* Total Findings */}
            <div className="flex items-center gap-4 rounded-2xl border border-orange-200/60 bg-[#fff9f4] p-4 shadow-sm">
              <div className="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-orange-100 text-orange-600">
                <FileSearch size={22} />
              </div>
              <div>
                <div className="text-2xl font-bold leading-none text-slate-900">
                  43
                </div>
                <div className="mt-1 text-xs font-bold text-slate-800">
                  Total Findings
                </div>
                <div className="mt-0.5 text-[11px] font-medium text-slate-500">
                  All status
                </div>
              </div>
            </div>

            {/* Open Recommendations */}
            <div className="flex items-center gap-4 rounded-2xl border border-blue-200/60 bg-[#f4f8ff] p-4 shadow-sm">
              <div className="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-blue-100 text-blue-600">
                <CalendarDays size={22} />
              </div>
              <div>
                <div className="text-2xl font-bold leading-none text-slate-900">
                  21
                </div>
                <div className="mt-1 text-xs font-bold text-slate-800">
                  Open Recommendations
                </div>
                <div className="mt-0.5 text-[11px] font-medium text-slate-500">
                  Requires action
                </div>
              </div>
            </div>

            {/* Overdue Recommendations */}
            <div className="flex items-center gap-4 rounded-2xl border border-rose-200/60 bg-[#fff5f5] p-4 shadow-sm">
              <div className="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-rose-100 text-rose-600">
                <AlertTriangle size={22} />
              </div>
              <div>
                <div className="text-2xl font-bold leading-none text-slate-900">
                  4
                </div>
                <div className="mt-1 text-xs font-bold text-slate-800">
                  Overdue Recommendations
                </div>
                <div className="mt-0.5 text-[11px] font-semibold text-rose-600">
                  Past target date
                </div>
              </div>
            </div>

            {/* Critical Findings */}
            <div className="flex items-center gap-4 rounded-2xl border border-purple-200/60 bg-[#faf5ff] p-4 shadow-sm">
              <div className="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-purple-100 text-purple-600">
                <Flag size={22} />
              </div>
              <div>
                <div className="text-2xl font-bold leading-none text-slate-900">
                  7
                </div>
                <div className="mt-1 text-xs font-bold text-slate-800">
                  Critical Findings
                </div>
                <div className="mt-0.5 text-[11px] font-medium text-slate-500">
                  High risk level
                </div>
              </div>
            </div>
          </div>

          {/* Navigation Tabs */}
          <div className="mt-6 flex items-center gap-6 border-b border-slate-200/80 pb-0">
            <button
              className={`pb-3 text-sm font-semibold transition-colors relative ${
                activeTab === "findings"
                  ? "text-[#ea580c]"
                  : "text-slate-500 hover:text-slate-800"
              }`}
              type="button"
              onClick={() => setActiveTab("findings")}
            >
              Recent Findings
              {activeTab === "findings" && (
                <span className="absolute bottom-0 left-0 right-0 h-0.5 rounded-full bg-[#ea580c]" />
              )}
            </button>

            <button
              className={`pb-3 text-sm font-semibold transition-colors relative ${
                activeTab === "recommendations"
                  ? "text-[#ea580c]"
                  : "text-slate-500 hover:text-slate-800"
              }`}
              type="button"
              onClick={() => setActiveTab("recommendations")}
            >
              Open Recommendations
              {activeTab === "recommendations" && (
                <span className="absolute bottom-0 left-0 right-0 h-0.5 rounded-full bg-[#ea580c]" />
              )}
            </button>
          </div>

          {/* Findings Table */}
          <div className="mt-4 overflow-x-auto">
            <table className="w-full text-left text-xs">
              <thead>
                <tr className="border-b border-slate-200/80 text-[11px] font-bold uppercase tracking-wider text-slate-400">
                  <th className="pb-3 pt-2">FINDING NO.</th>
                  <th className="pb-3 pt-2">FINDING</th>
                  <th className="pb-3 pt-2">ENGAGEMENT</th>
                  <th className="pb-3 pt-2">OFFICE</th>
                  <th className="pb-3 pt-2">RISK LEVEL</th>
                  <th className="pb-3 pt-2">STATUS</th>
                  <th className="pb-3 pt-2">DATE ISSUED</th>
                  <th className="pb-3 pt-2 text-right">ACTION</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 font-medium text-slate-700">
                {findingsData.map((row) => (
                  <tr
                    key={row.findingNo}
                    className="transition hover:bg-slate-50/80"
                  >
                    <td className="py-3.5 font-bold text-[#ea580c]">
                      {row.findingNo}
                    </td>
                    <td className="py-3.5 font-medium text-slate-900 max-w-[210px] truncate">
                      {row.finding}
                    </td>
                    <td className="py-3.5 font-bold text-[#0284c7]">
                      {row.engagement}
                    </td>
                    <td className="py-3.5 text-slate-700 font-medium">
                      {row.office}
                    </td>
                    <td className="py-3.5">
                      <span
                        className={`inline-flex items-center rounded-full border px-2.5 py-0.5 text-[11px] font-semibold ${row.riskBadge}`}
                      >
                        {row.riskLevel}
                      </span>
                    </td>
                    <td className="py-3.5">
                      <span
                        className={`inline-flex items-center rounded-full border px-2.5 py-0.5 text-[11px] font-semibold ${row.statusBadge}`}
                      >
                        {row.status}
                      </span>
                    </td>
                    <td className="py-3.5 text-slate-600">{row.dateIssued}</td>
                    <td className="py-3.5 text-right">
                      <button
                        className="p-1 text-slate-400 transition hover:text-slate-700"
                        title="View Finding Details"
                        type="button"
                        onClick={() => handleRowClick(row.findingNo)}
                      >
                        <Eye size={16} />
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>

        {/* Modal Footer */}
        <div className="flex items-center justify-between border-t border-slate-100 px-7 py-4">
          <div className="flex items-center gap-2.5 rounded-xl border border-sky-100 bg-[#f0f7ff] px-4 py-2.5 text-xs font-semibold text-sky-700">
            <Info size={16} className="shrink-0 text-sky-600" />
            <span>Click on a finding to view details.</span>
          </div>

          <div className="flex items-center gap-3">
            <Button
              className="rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 shadow-none hover:bg-slate-50 transition-colors"
              onClick={onClose}
            >
              Close
            </Button>
            <Button
              className="flex items-center gap-2 rounded-xl bg-[#ea580c] px-5 py-2.5 text-sm font-semibold text-white shadow-none hover:bg-[#c2410c] transition-colors"
              onClick={handleViewAll}
            >
              <span>View All Findings</span>
              <ArrowRight size={16} />
            </Button>
          </div>
        </div>
      </div>
    </div>,
    document.body,
  );
}
