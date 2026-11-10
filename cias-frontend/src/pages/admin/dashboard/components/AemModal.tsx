import {
  ArrowRight,
  CalendarDays,
  CheckCircle2,
  ChevronLeft,
  ChevronRight,
  ChevronsRight,
  CirclePlay,
  Eye,
  FileText,
  Filter,
  MoreVertical,
  Pencil,
  Search,
  Settings,
  ShieldCheck,
  X,
} from "lucide-react";
import { useState } from "react";
import { createPortal } from "react-dom";
import { Button } from "../../../../components/ui/button";
import { useToast } from "../../../../components/ui/toast";
import { navigateTo } from "../../../../routes";

type AemModalProps = {
  isOpen: boolean;
  onClose: () => void;
};

const engagementsData = [
  {
    id: "AEMS-2025-009",
    title: "Audit of Procurement Process",
    office: "CHUDD",
    stage: "Execution",
    stageBadge: "bg-emerald-100 text-emerald-700 border-emerald-200",
    status: "In Progress",
    statusBadge: "bg-blue-100 text-blue-700 border-blue-200",
    riskLevel: "High",
    riskBadge: "bg-orange-100 text-orange-700 border-orange-200",
    progress: 45,
    targetEnd: "Jul 15, 2025",
    head: "Maria Santos",
    teamSize: 5,
  },
  {
    id: "AEMS-2025-008",
    title: "Projects Implementation Audit",
    office: "City Engineer's Office",
    stage: "Execution",
    stageBadge: "bg-emerald-100 text-emerald-700 border-emerald-200",
    status: "In Progress",
    statusBadge: "bg-blue-100 text-blue-700 border-blue-200",
    riskLevel: "High",
    riskBadge: "bg-orange-100 text-orange-700 border-orange-200",
    progress: 68,
    targetEnd: "Jun 30, 2025",
    head: "Roberto Gomez",
    teamSize: 4,
  },
  {
    id: "AEMS-2025-007",
    title: "Business Permits and Licensing Audit",
    office: "BPLD",
    stage: "Reporting",
    stageBadge: "bg-blue-100 text-blue-700 border-blue-200",
    status: "In Progress",
    statusBadge: "bg-blue-100 text-blue-700 border-blue-200",
    riskLevel: "Medium",
    riskBadge: "bg-amber-100 text-amber-700 border-amber-200",
    progress: 85,
    targetEnd: "Jun 10, 2025",
    head: "Anna Reyes",
    teamSize: 3,
  },
  {
    id: "AEMS-2025-006",
    title: "IT General Controls Audit",
    office: "CIO",
    stage: "Planning",
    stageBadge: "bg-slate-100 text-slate-700 border-slate-200",
    status: "Planned",
    statusBadge: "bg-slate-100 text-slate-700 border-slate-200",
    riskLevel: "Medium",
    riskBadge: "bg-amber-100 text-amber-700 border-amber-200",
    progress: 15,
    targetEnd: "Aug 20, 2025",
    head: "Mark Tan",
    teamSize: 4,
  },
  {
    id: "AEMS-2025-005",
    title: "Cash and Bank Management Audit",
    office: "City Treasurer's Office",
    stage: "Reporting",
    stageBadge: "bg-blue-100 text-blue-700 border-blue-200",
    status: "In Progress",
    statusBadge: "bg-blue-100 text-blue-700 border-blue-200",
    riskLevel: "Medium",
    riskBadge: "bg-amber-100 text-amber-700 border-amber-200",
    progress: 90,
    targetEnd: "May 30, 2025",
    head: "Elena Ramos",
    teamSize: 6,
  },
];

export function AemModal({ isOpen, onClose }: AemModalProps) {
  const { showToast } = useToast();
  const [activeTab, setActiveTab] = useState<
    "engagements" | "activities" | "assignments"
  >("engagements");
  const [selectedEngagement, setSelectedEngagement] = useState(
    engagementsData[0],
  );
  const [searchQuery, setSearchQuery] = useState("");

  if (!isOpen || typeof document === "undefined") {
    return null;
  }

  const handleGoToModule = () => {
    showToast({
      title: "Audit Engagement Management (AEM)",
      description: "Opening AEM module workspace preview.",
      tone: "info",
    });
    onClose();
    navigateTo("/admin/audit-engagements");
  };

  return createPortal(
    <div
      aria-modal="true"
      role="dialog"
      className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 p-4 backdrop-blur-[2px]"
      onClick={onClose}
    >
      <div
        className="relative flex max-h-[calc(100vh-2rem)] w-full max-w-[1080px] flex-col overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-[0_24px_80px_rgba(15,23,42,0.25)] animate-[page-enter_200ms_cubic-bezier(0.22,1,0.36,1)]"
        onClick={(e) => e.stopPropagation()}
      >
        {/* Header */}
        <div className="flex items-start justify-between border-b border-slate-100 px-7 pb-5 pt-6">
          <div className="flex items-center gap-4">
            <div className="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-emerald-50 text-emerald-600 border border-emerald-100 shadow-sm">
              <ShieldCheck size={26} strokeWidth={2} />
            </div>
            <div>
              <h2 className="text-xl font-bold tracking-tight text-slate-900">
                Audit Engagement Management (AEM)
              </h2>
              <p className="mt-0.5 text-sm text-slate-500 font-normal">
                Manage audit engagements from planning to completion.
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

        {/* Scrollable Body */}
        <div className="flex-1 overflow-y-auto px-7 py-6">
          {/* Top 5 Stat Cards */}
          <div className="grid grid-cols-1 gap-3.5 sm:grid-cols-2 lg:grid-cols-5">
            {/* Total Engagements */}
            <div className="flex items-center gap-3 rounded-2xl border border-emerald-200/60 bg-[#f4fcf6] p-3.5 shadow-sm">
              <div className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-emerald-100 text-emerald-700">
                <CalendarDays size={20} />
              </div>
              <div>
                <div className="text-xl font-bold leading-none text-slate-900">
                  18
                </div>
                <div className="mt-1 text-[11px] font-bold text-slate-800">
                  Total Engagements
                </div>
                <div className="mt-0.5 text-[10px] text-slate-500">
                  All status
                </div>
              </div>
            </div>

            {/* Planning */}
            <div className="flex items-center gap-3 rounded-2xl border border-emerald-200/40 bg-[#f9fdfa] p-3.5 shadow-sm">
              <div className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-emerald-50 text-emerald-600">
                <CirclePlay size={20} />
              </div>
              <div>
                <div className="text-xl font-bold leading-none text-slate-900">
                  6
                </div>
                <div className="mt-1 text-[11px] font-bold text-slate-800">
                  Planning
                </div>
                <div className="mt-0.5 text-[10px] text-slate-500">
                  33% of total
                </div>
              </div>
            </div>

            {/* In Execution */}
            <div className="flex items-center gap-3 rounded-2xl border border-amber-200/40 bg-[#fffdf9] p-3.5 shadow-sm">
              <div className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-amber-50 text-amber-600">
                <Settings size={20} />
              </div>
              <div>
                <div className="text-xl font-bold leading-none text-slate-900">
                  7
                </div>
                <div className="mt-1 text-[11px] font-bold text-slate-800">
                  In Execution
                </div>
                <div className="mt-0.5 text-[10px] text-slate-500">
                  39% of total
                </div>
              </div>
            </div>

            {/* In Reporting */}
            <div className="flex items-center gap-3 rounded-2xl border border-blue-200/40 bg-[#f8fbff] p-3.5 shadow-sm">
              <div className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-blue-50 text-blue-600">
                <FileText size={20} />
              </div>
              <div>
                <div className="text-xl font-bold leading-none text-slate-900">
                  3
                </div>
                <div className="mt-1 text-[11px] font-bold text-slate-800">
                  In Reporting
                </div>
                <div className="mt-0.5 text-[10px] text-slate-500">
                  17% of total
                </div>
              </div>
            </div>

            {/* Completed */}
            <div className="flex items-center gap-3 rounded-2xl border border-purple-200/40 bg-[#faf8ff] p-3.5 shadow-sm">
              <div className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-purple-50 text-purple-600">
                <Pencil size={20} />
              </div>
              <div>
                <div className="text-xl font-bold leading-none text-slate-900">
                  2
                </div>
                <div className="mt-1 text-[11px] font-bold text-slate-800">
                  Completed
                </div>
                <div className="mt-0.5 text-[10px] text-slate-500">
                  11% of total
                </div>
              </div>
            </div>
          </div>

          {/* Navigation Tabs */}
          <div className="mt-6 flex items-center gap-6 border-b border-slate-200/80 pb-0">
            <button
              className={`pb-3 text-sm font-semibold transition-colors relative ${
                activeTab === "engagements"
                  ? "text-[#16a34a]"
                  : "text-slate-500 hover:text-slate-800"
              }`}
              type="button"
              onClick={() => setActiveTab("engagements")}
            >
              Engagements
              {activeTab === "engagements" && (
                <span className="absolute bottom-0 left-0 right-0 h-0.5 rounded-full bg-[#16a34a]" />
              )}
            </button>

            <button
              className={`pb-3 text-sm font-semibold transition-colors relative ${
                activeTab === "activities"
                  ? "text-[#16a34a]"
                  : "text-slate-500 hover:text-slate-800"
              }`}
              type="button"
              onClick={() => setActiveTab("activities")}
            >
              Upcoming Activities
              {activeTab === "activities" && (
                <span className="absolute bottom-0 left-0 right-0 h-0.5 rounded-full bg-[#16a34a]" />
              )}
            </button>

            <button
              className={`pb-3 text-sm font-semibold transition-colors relative ${
                activeTab === "assignments"
                  ? "text-[#16a34a]"
                  : "text-slate-500 hover:text-slate-800"
              }`}
              type="button"
              onClick={() => setActiveTab("assignments")}
            >
              My Assignments
              {activeTab === "assignments" && (
                <span className="absolute bottom-0 left-0 right-0 h-0.5 rounded-full bg-[#16a34a]" />
              )}
            </button>
          </div>

          {/* Search & Filter Toolbar */}
          <div className="mt-4 flex flex-wrap items-center justify-between gap-3">
            <div className="relative flex-1 min-w-[220px]">
              <Search
                size={16}
                className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"
              />
              <input
                className="h-9 w-full rounded-xl border border-slate-200 bg-slate-50/70 pl-9 pr-3 text-xs text-slate-700 placeholder:text-slate-400 focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-1 focus:ring-emerald-500"
                placeholder="Search engagement..."
                type="text"
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
              />
            </div>

            <div className="flex flex-wrap items-center gap-2 text-xs">
              <select className="h-9 rounded-xl border border-slate-200 bg-white px-3 text-slate-700 font-medium focus:outline-none">
                <option>Status: All</option>
                <option>In Progress</option>
                <option>Planned</option>
                <option>Completed</option>
              </select>

              <select className="h-9 rounded-xl border border-slate-200 bg-white px-3 text-slate-700 font-medium focus:outline-none">
                <option>Office: All</option>
                <option>CHUDD</option>
                <option>City Engineer's Office</option>
                <option>BPLD</option>
                <option>CIO</option>
              </select>

              <select className="h-9 rounded-xl border border-slate-200 bg-white px-3 text-slate-700 font-medium focus:outline-none">
                <option>Year: 2025</option>
                <option>2024</option>
              </select>

              <select className="h-9 rounded-xl border border-slate-200 bg-white px-3 text-slate-700 font-medium focus:outline-none">
                <option>Risk Level: All</option>
                <option>High</option>
                <option>Medium</option>
                <option>Critical</option>
              </select>

              <button
                className="flex h-9 items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 font-semibold text-slate-700 hover:bg-slate-50 transition-colors"
                type="button"
              >
                <Filter size={14} />
                <span>Filters</span>
              </button>
            </div>
          </div>

          {/* Engagements Table */}
          <div className="mt-4 overflow-x-auto">
            <table className="w-full text-left text-xs">
              <thead>
                <tr className="border-b border-slate-200/80 text-[11px] font-bold uppercase tracking-wider text-slate-400">
                  <th className="pb-3 pt-2">ENGAGEMENT NO.</th>
                  <th className="pb-3 pt-2">ENGAGEMENT TITLE</th>
                  <th className="pb-3 pt-2">OFFICE</th>
                  <th className="pb-3 pt-2">STAGE</th>
                  <th className="pb-3 pt-2">STATUS</th>
                  <th className="pb-3 pt-2">RISK LEVEL</th>
                  <th className="pb-3 pt-2">PROGRESS</th>
                  <th className="pb-3 pt-2">TARGET END</th>
                  <th className="pb-3 pt-2 text-right">ACTIONS</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 font-medium text-slate-700">
                {engagementsData.map((row) => (
                  <tr
                    key={row.id}
                    className={`transition cursor-pointer ${
                      selectedEngagement.id === row.id
                        ? "bg-emerald-50/40"
                        : "hover:bg-slate-50/80"
                    }`}
                    onClick={() => setSelectedEngagement(row)}
                  >
                    <td className="py-3 font-bold text-[#0284c7]">
                      {row.id}
                    </td>
                    <td className="py-3 font-bold text-slate-900 max-w-[210px] truncate">
                      {row.title}
                    </td>
                    <td className="py-3 text-slate-700 font-medium">
                      {row.office}
                    </td>
                    <td className="py-3">
                      <span
                        className={`inline-flex items-center rounded-md px-2 py-0.5 text-[11px] font-semibold ${row.stageBadge}`}
                      >
                        {row.stage}
                      </span>
                    </td>
                    <td className="py-3">
                      <span
                        className={`inline-flex items-center rounded-md px-2 py-0.5 text-[11px] font-semibold ${row.statusBadge}`}
                      >
                        {row.status}
                      </span>
                    </td>
                    <td className="py-3">
                      <span
                        className={`inline-flex items-center rounded-md px-2 py-0.5 text-[11px] font-semibold ${row.riskBadge}`}
                      >
                        {row.riskLevel}
                      </span>
                    </td>
                    <td className="py-3">
                      <div className="flex items-center gap-2 min-w-[100px]">
                        <div className="h-1.5 w-16 overflow-hidden rounded-full bg-slate-100">
                          <div
                            className="h-full rounded-full bg-emerald-600"
                            style={{ width: `${row.progress}%` }}
                          />
                        </div>
                        <span className="text-[11px] font-semibold text-slate-600">
                          {row.progress}%
                        </span>
                      </div>
                    </td>
                    <td className="py-3 text-slate-600">{row.targetEnd}</td>
                    <td className="py-3 text-right">
                      <div className="flex items-center justify-end gap-1">
                        <button
                          className="p-1 text-slate-400 hover:text-slate-700 transition"
                          title="View Engagement"
                          type="button"
                        >
                          <Eye size={15} />
                        </button>
                        <button
                          className="p-1 text-slate-400 hover:text-slate-700 transition"
                          title="Options"
                          type="button"
                        >
                          <MoreVertical size={15} />
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {/* Table Pagination Bar */}
          <div className="mt-3 flex items-center justify-between text-xs text-slate-500 border-t border-slate-100 pt-3">
            <span>Showing 1 to 5 of 18 engagements</span>
            <div className="flex items-center gap-1 font-semibold text-slate-600">
              <button
                className="grid h-7 w-7 place-items-center rounded-lg border border-slate-200 hover:bg-slate-50 text-slate-400"
                type="button"
              >
                <ChevronLeft size={14} />
              </button>
              <button
                className="grid h-7 w-7 place-items-center rounded-lg bg-emerald-100 text-emerald-700 font-bold"
                type="button"
              >
                1
              </button>
              <button
                className="grid h-7 w-7 place-items-center rounded-lg border border-slate-200 hover:bg-slate-50"
                type="button"
              >
                2
              </button>
              <button
                className="grid h-7 w-7 place-items-center rounded-lg border border-slate-200 hover:bg-slate-50"
                type="button"
              >
                3
              </button>
              <button
                className="grid h-7 w-7 place-items-center rounded-lg border border-slate-200 hover:bg-slate-50"
                type="button"
              >
                4
              </button>
              <button
                className="grid h-7 w-7 place-items-center rounded-lg border border-slate-200 hover:bg-slate-50"
                type="button"
              >
                <ChevronRight size={14} />
              </button>
              <button
                className="grid h-7 w-7 place-items-center rounded-lg border border-slate-200 hover:bg-slate-50"
                type="button"
              >
                <ChevronsRight size={14} />
              </button>
            </div>
          </div>

          {/* Selected Engagement Detail Card Panel */}
          <div className="mt-5 grid grid-cols-1 gap-4 rounded-2xl border border-slate-200/80 bg-slate-50/60 p-4 lg:grid-cols-12">
            {/* Left Summary */}
            <div className="lg:col-span-4 space-y-2 border-r border-slate-200/80 pr-4 max-lg:border-r-0 max-lg:border-b max-lg:pb-4">
              <h3 className="text-xs font-bold uppercase tracking-wider text-slate-500">
                Engagement Summary
              </h3>
              <div className="text-sm font-bold text-[#0284c7]">
                {selectedEngagement.id}
              </div>
              <div className="text-xs font-bold text-slate-900">
                {selectedEngagement.title}
              </div>
              <div className="text-xs text-slate-500 font-medium">
                {selectedEngagement.office}
              </div>

              <div className="mt-3 space-y-1.5 text-xs pt-2 border-t border-slate-200/60">
                <div className="flex justify-between">
                  <span className="text-slate-500">Engagement Head</span>
                  <span className="font-semibold text-slate-800">
                    {selectedEngagement.head}
                  </span>
                </div>
                <div className="flex justify-between">
                  <span className="text-slate-500">Target End Date</span>
                  <span className="font-semibold text-slate-800">
                    {selectedEngagement.targetEnd}
                  </span>
                </div>
                <div className="flex justify-between">
                  <span className="text-slate-500">Team Size</span>
                  <span className="font-semibold text-slate-800">
                    {selectedEngagement.teamSize}
                  </span>
                </div>
                <div className="flex justify-between items-center">
                  <span className="text-slate-500">Risk Level</span>
                  <span
                    className={`inline-flex items-center rounded-md px-2 py-0.5 text-[10px] font-semibold ${selectedEngagement.riskBadge}`}
                  >
                    {selectedEngagement.riskLevel}
                  </span>
                </div>
              </div>
            </div>

            {/* Middle Stepper & Recent Activities */}
            <div className="lg:col-span-5 space-y-4 border-r border-slate-200/80 px-2 max-lg:border-r-0 max-lg:border-b max-lg:pb-4">
              <div>
                <h3 className="text-xs font-bold uppercase tracking-wider text-slate-500 mb-3">
                  Engagement Stage
                </h3>
                <div className="flex items-center justify-between text-center relative px-2">
                  <div className="absolute left-6 right-6 top-3 h-0.5 bg-slate-200 -z-0" />

                  <div className="relative z-10 flex flex-col items-center">
                    <div className="h-6 w-6 rounded-full bg-emerald-600 text-white flex items-center justify-center">
                      <CheckCircle2 size={16} />
                    </div>
                    <span className="mt-1 text-[11px] font-bold text-emerald-700">
                      Planning
                    </span>
                    <span className="text-[9px] text-slate-400">Completed</span>
                  </div>

                  <div className="relative z-10 flex flex-col items-center">
                    <div className="h-6 w-6 rounded-full bg-emerald-600 text-white font-bold text-xs flex items-center justify-center">
                      2
                    </div>
                    <span className="mt-1 text-[11px] font-bold text-emerald-700">
                      Execution
                    </span>
                    <span className="text-[9px] text-emerald-600 font-semibold">
                      In Progress
                    </span>
                  </div>

                  <div className="relative z-10 flex flex-col items-center">
                    <div className="h-6 w-6 rounded-full bg-slate-200 text-slate-500 font-bold text-xs flex items-center justify-center">
                      3
                    </div>
                    <span className="mt-1 text-[11px] font-medium text-slate-400">
                      Reporting
                    </span>
                    <span className="text-[9px] text-slate-400">Pending</span>
                  </div>

                  <div className="relative z-10 flex flex-col items-center">
                    <div className="h-6 w-6 rounded-full bg-slate-200 text-slate-500 font-bold text-xs flex items-center justify-center">
                      4
                    </div>
                    <span className="mt-1 text-[11px] font-medium text-slate-400">
                      Closure
                    </span>
                    <span className="text-[9px] text-slate-400">Pending</span>
                  </div>
                </div>
              </div>

              <div className="pt-2 border-t border-slate-200/60">
                <h4 className="text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-2">
                  Recent Activities
                </h4>
                <div className="space-y-2 text-xs">
                  <div className="flex items-start gap-2">
                    <div className="mt-0.5 h-2 w-2 rounded-full bg-emerald-500 shrink-0" />
                    <div>
                      <p className="font-semibold text-slate-800">
                        Working papers uploaded
                      </p>
                      <p className="text-[10px] text-slate-400">
                        Juan dela Cruz • May 20, 2025 10:15 AM
                      </p>
                    </div>
                  </div>

                  <div className="flex items-start gap-2">
                    <div className="mt-0.5 h-2 w-2 rounded-full bg-emerald-500 shrink-0" />
                    <div>
                      <p className="font-semibold text-slate-800">
                        Entrance conference conducted
                      </p>
                      <p className="text-[10px] text-slate-400">
                        Juan dela Cruz • May 16, 2025 09:00 AM
                      </p>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            {/* Right Tasks */}
            <div className="lg:col-span-3 space-y-2 pl-2">
              <h3 className="text-xs font-bold uppercase tracking-wider text-slate-500">
                My Tasks (2)
              </h3>
              <div className="space-y-2.5 pt-1">
                <div className="rounded-xl border border-slate-200 bg-white p-2.5 text-xs space-y-1">
                  <label className="flex items-start gap-2 cursor-pointer">
                    <input
                      type="checkbox"
                      className="mt-0.5 h-3.5 w-3.5 accent-emerald-600 rounded"
                    />
                    <span className="font-medium text-slate-800 leading-snug">
                      Prepare audit program for completeness testing
                    </span>
                  </label>
                  <p className="text-[10px] font-semibold text-rose-600 pl-5">
                    Due: May 22, 2025
                  </p>
                </div>

                <div className="rounded-xl border border-slate-200 bg-white p-2.5 text-xs space-y-1">
                  <label className="flex items-start gap-2 cursor-pointer">
                    <input
                      type="checkbox"
                      className="mt-0.5 h-3.5 w-3.5 accent-emerald-600 rounded"
                    />
                    <span className="font-medium text-slate-800 leading-snug">
                      Review sampled procurement documents
                    </span>
                  </label>
                  <p className="text-[10px] font-semibold text-rose-600 pl-5">
                    Due: May 25, 2025
                  </p>
                </div>

                <button
                  className="text-[11px] font-bold text-emerald-700 hover:text-emerald-800 flex items-center gap-1 transition"
                  type="button"
                >
                  <span>View all tasks</span>
                  <ArrowRight size={12} />
                </button>
              </div>
            </div>
          </div>
        </div>

        {/* Modal Footer */}
        <div className="flex items-center justify-end gap-3 border-t border-slate-100 px-7 py-4">
          <Button
            className="rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 shadow-none hover:bg-slate-50 transition-colors"
            onClick={onClose}
          >
            Close
          </Button>
          <Button
            className="flex items-center gap-2 rounded-xl bg-[#16a34a] px-5 py-2.5 text-sm font-semibold text-white shadow-none hover:bg-[#15803d] transition-colors"
            onClick={handleGoToModule}
          >
            <span>Go to AEM Module</span>
            <ArrowRight size={16} />
          </Button>
        </div>
      </div>
    </div>,
    document.body,
  );
}
