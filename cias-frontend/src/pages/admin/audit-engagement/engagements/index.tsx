import { useEffect, useMemo, useState } from "react";
import {
  AlertTriangle,
  ChevronRight,
  Filter,
  Loader2,
  MoreHorizontal,
  Plus,
  Search,
  X,
} from "lucide-react";
import { Button } from "../../../../components/ui/button";
import { AdminLayout } from "../../../../layouts/admin/AdminLayout";
import { useToast } from "../../../../components/ui/toast";
import { navigateTo } from "../../../../routes";
import {
  fetchAemsDashboardSummary,
  fetchAuditEngagementRecords,
  getAuditEngagementRecords,
  getCachedAemsDashboard,
  type AemsDashboardSummary,
} from "../service";
import type { AuditEngagementRecord } from "../audit-engagement-types";
import { cn } from "../../../../utils/helpers";

export function AdminEngagementsPage() {
  const { showToast } = useToast();
  const [search, setSearch] = useState("");
  const [showFilters, setShowFilters] = useState(false);
  const [officeFilter, setOfficeFilter] = useState("All");
  const [phaseFilter, setPhaseFilter] = useState("All");
  const [statusFilter] = useState("All");
  const [healthFilter, setHealthFilter] = useState("All");
  const [attentionFilter, setAttentionFilter] = useState<
    "overdue" | "reporting" | "planning" | "all" | null
  >(null);
  const [sortBy, setSortBy] = useState("code-asc");
  const [pageSize, setPageSize] = useState(10);
  const [currentPage, setCurrentPage] = useState(1);
  const [activeMenuId, setActiveMenuId] = useState<string | null>(null);

  // Load live engagements and AEMS dashboard metrics from backend API
  const [rawRecords, setRawRecords] = useState<AuditEngagementRecord[]>(() =>
    getAuditEngagementRecords()
  );
  const [aemsDashboard, setAemsDashboard] = useState<AemsDashboardSummary | null>(() =>
    getCachedAemsDashboard()
  );
  const [isLoading, setIsLoading] = useState(() => rawRecords.length === 0);
  const [isAttentionLoading, setIsAttentionLoading] = useState(() => !aemsDashboard);

  useEffect(() => {
    let isMounted = true;
    setIsLoading(true);
    setIsAttentionLoading(true);

    (async () => {
      try {
        const records = await fetchAuditEngagementRecords();
        if (isMounted) {
          setRawRecords(records);
          setIsLoading(false);
        }

        // Load AEMS dashboard sequentially to avoid queuing against single-threaded dev server
        const dashboard = await fetchAemsDashboardSummary();
        if (isMounted) {
          setAemsDashboard(dashboard);
        }
      } catch (err) {
        console.warn("Could not load backend engagements:", err);
      } finally {
        if (isMounted) {
          setIsLoading(false);
          setIsAttentionLoading(false);
        }
      }
    })();

    return () => {
      isMounted = false;
    };
  }, []);

  // Merge live health computed by AEMS dashboard into engagement rows
  const allRecords = useMemo(() => {
    if (!aemsDashboard?.engagements?.length) return rawRecords;
    const dashMap = new Map(aemsDashboard.engagements.map((e) => [String(e.id), e]));
    return rawRecords.map((record) => {
      const dashItem = dashMap.get(String(record.id));
      if (!dashItem) return record;
      const mappedHealth: AuditEngagementRecord["health"] =
        dashItem.health === "OVERDUE" || dashItem.health === "BLOCKED"
          ? "Delayed"
          : dashItem.alerts && dashItem.alerts.length > 0
          ? "At Risk"
          : "On Track";
      return {
        ...record,
        health: mappedHealth,
      };
    });
  }, [rawRecords, aemsDashboard]);

  // Real Needs Attention calculations from backend dashboard & live engagements
  const needsAttention = useMemo(() => {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const thirtyDaysMs = 30 * 86_400_000;

    const dashEngagements = aemsDashboard?.engagements || [];
    const overdueIds = new Set<string>();
    const reportingIds = new Set<string>();
    const planningIds = new Set<string>();

    if (dashEngagements.length > 0) {
      dashEngagements.forEach((eng) => {
        const idStr = String(eng.id);
        const upperStatus = (eng.status || "").toUpperCase();
        const isClosed = ["ISSUED", "CLOSED", "CANCELLED"].includes(upperStatus);

        if (
          eng.health === "OVERDUE" ||
          eng.health === "BLOCKED" ||
          (eng.alerts && eng.alerts.length > 0)
        ) {
          overdueIds.add(idStr);
        }

        const targetDateStr = eng.expectedReportDate || eng.plannedEndDate;
        if (!isClosed && targetDateStr) {
          const targetDate = new Date(`${targetDateStr}T00:00:00`);
          if (!Number.isNaN(targetDate.getTime())) {
            const diffMs = targetDate.getTime() - today.getTime();
            if (diffMs <= thirtyDaysMs) {
              reportingIds.add(idStr);
            }
          }
        }

        const planningStages = (eng.stages || []).filter((s) =>
          ["aeo", "aep", "auditProgram"].includes(s.key)
        );
        const hasIncompletePlanning =
          upperStatus === "ENGAGEMENT_PLANNING" ||
          upperStatus === "DRAFT" ||
          upperStatus === "AUTHORIZATION_PREPARATION" ||
          upperStatus === "RETURNED_FOR_REVISION" ||
          planningStages.some((s) => s.percent < 100);
        if (!isClosed && hasIncompletePlanning) {
          planningIds.add(idStr);
        }
      });
    } else {
      allRecords.forEach((record) => {
        const idStr = String(record.id);
        const upperStatus = (record.status || "").toUpperCase();
        const isClosed = ["ISSUED", "CLOSED", "CANCELLED"].includes(upperStatus);
        if (record.health === "Delayed" || record.health === "At Risk") {
          overdueIds.add(idStr);
        }
        if (!isClosed && record.endDate) {
          const targetDate = new Date(`${record.endDate}T00:00:00`);
          if (!Number.isNaN(targetDate.getTime()) && targetDate.getTime() - today.getTime() <= thirtyDaysMs) {
            reportingIds.add(idStr);
          }
        }
        if ((record.phase || "").toLowerCase() === "planning") {
          planningIds.add(idStr);
        }
      });
    }

    const cards = aemsDashboard?.cards;
    const overdueReviewsCount = cards
      ? (cards.overdueProcedures || 0) +
        (cards.workingPapersAwaitingReview || 0) +
        (cards.findingsAwaitingReview || 0) +
        (cards.reportsPendingApproval || 0)
      : overdueIds.size;

    const reportingCount = reportingIds.size;
    const planningCount = cards
      ? Math.max(cards.engagementsInPlanning || 0, planningIds.size)
      : planningIds.size;

    return {
      overdueCount: overdueReviewsCount,
      reportingCount,
      planningCount,
      totalCount: overdueReviewsCount + reportingCount + planningCount,
      overdueIds,
      reportingIds,
      planningIds,
    };
  }, [aemsDashboard, allRecords]);

  // Filter records based on search and selected filters
  const filteredRecords = useMemo(() => {
    const q = search.trim().toLowerCase();

    return allRecords.filter((record) => {
      const code = (record.code || "").toLowerCase();
      const title = (record.title || record.auditArea || record.subject || "").toLowerCase();
      const office = (record.office || "").toLowerCase();
      const leader = (record.leadAuditor || "").toLowerCase();
      const phase = (record.phase || "").toLowerCase();

      const matchesSearch =
        q.length === 0 ||
        code.includes(q) ||
        title.includes(q) ||
        office.includes(q) ||
        leader.includes(q) ||
        phase.includes(q);

      const matchesOffice =
        officeFilter === "All" || record.office === officeFilter;
      const matchesPhase =
        phaseFilter === "All" || (record.phase || "Planning") === phaseFilter;
      const matchesStatus =
        statusFilter === "All" || record.status === statusFilter;
      const matchesHealth =
        healthFilter === "All" || (record.health || "On Track") === healthFilter;

      const idStr = String(record.id);
      const matchesAttention =
        !attentionFilter ||
        (attentionFilter === "overdue" && needsAttention.overdueIds.has(idStr)) ||
        (attentionFilter === "reporting" && needsAttention.reportingIds.has(idStr)) ||
        (attentionFilter === "planning" && needsAttention.planningIds.has(idStr)) ||
        (attentionFilter === "all" &&
          (needsAttention.overdueIds.has(idStr) ||
            needsAttention.reportingIds.has(idStr) ||
            needsAttention.planningIds.has(idStr)));

      return (
        matchesSearch &&
        matchesOffice &&
        matchesPhase &&
        matchesStatus &&
        matchesHealth &&
        matchesAttention
      );
    });
  }, [
    allRecords,
    search,
    officeFilter,
    phaseFilter,
    statusFilter,
    healthFilter,
    attentionFilter,
    needsAttention,
  ]);

  // Sort records
  const sortedRecords = useMemo(() => {
    const items = [...filteredRecords];
    switch (sortBy) {
      case "code-asc":
        return items.sort((a, b) => (a.code || "").localeCompare(b.code || ""));
      case "code-desc":
        return items.sort((a, b) => (b.code || "").localeCompare(a.code || ""));
      case "title-asc":
        return items.sort((a, b) => {
          const tA = a.title || a.auditArea || a.subject || "";
          const tB = b.title || b.auditArea || b.subject || "";
          return tA.localeCompare(tB);
        });
      case "updated-desc":
        return items.sort((a, b) => (b.lastUpdated || "").localeCompare(a.lastUpdated || ""));
      case "updated-asc":
        return items.sort((a, b) => (a.lastUpdated || "").localeCompare(b.lastUpdated || ""));
      default:
        return items;
    }
  }, [filteredRecords, sortBy]);

  // Paginate records
  const totalCount = sortedRecords.length;
  const totalPages = Math.max(1, Math.ceil(totalCount / pageSize));
  const validCurrentPage = Math.min(currentPage, totalPages);
  const startIndex = (validCurrentPage - 1) * pageSize;
  const endIndex = Math.min(startIndex + pageSize, totalCount);
  const displayedRecords = sortedRecords.slice(startIndex, endIndex);

  // Unique filter options
  const uniqueOffices = useMemo(() => {
    return Array.from(new Set(allRecords.map((r) => r.office))).filter(Boolean);
  }, [allRecords]);

  const handleOpenEngagement = (record: AuditEngagementRecord) => {
    navigateTo(`/admin/audit-engagement/${record.id}`);
  };

  const getHealthBadge = (health?: string) => {
    switch (health) {
      case "Delayed":
        return <span className="font-semibold text-rose-600">Delayed</span>;
      case "At Risk":
        return <span className="font-semibold text-amber-600">At Risk</span>;
      case "On Track":
      default:
        return <span className="font-semibold text-emerald-600">On Track</span>;
    }
  };

  const getStatusText = (status: string) => {
    if (status === "Active") return "In Progress";
    if (status === "For Approval") return "Under Review";
    if (status === "Completed") return "For Closure";
    if (status === "Draft") return "In Progress";
    return status;
  };

  return (
    <AdminLayout>
      <div className="space-y-3 pb-10">
        {/* Top Breadcrumb */}
        <div className="flex items-center gap-1.5 text-xs text-slate-500">
          <button
            onClick={() => navigateTo("/admin/dashboard")}
            className="hover:text-blue-600 transition cursor-pointer"
            type="button"
          >
            Home
          </button>
          <span>&gt;</span>
          <span className="text-slate-700">Audit Engagements Workspace</span>
        </div>

        {/* Page Title & Action Header */}
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between pt-0.5">
          <div>
            <div className="flex items-center gap-2">
              <h1 className="text-xl font-bold text-[#103565] tracking-tight">
                Audit Engagements Workspace
              </h1>
              <span className="text-xs font-semibold text-[#dc2626]">
                [ WF-SCR-200-01 ]
              </span>
            </div>
            <p className="text-xs text-slate-500 mt-0.5">
              Search, filter, and manage audit engagements. Select an engagement to view details and access its component workspaces.
            </p>
          </div>

          <div className="flex items-center gap-2">
            <Button
              className="h-8 rounded-md bg-[#1877f2] hover:bg-[#166fe5] text-white px-3.5 text-xs font-semibold shadow-2xs flex items-center gap-1.5 shrink-0 transition cursor-pointer"
              onClick={() => navigateTo("/admin/audit-engagement/create")}
              type="button"
            >
              <Plus size={15} strokeWidth={2.5} />
              <span>New Audit Engagement</span>
            </Button>
          </div>
        </div>

        {/* Centered Pill Search Bar */}
        <div className="py-1 flex justify-center px-1">
          <div className="relative w-full max-w-xl">
            <div className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-sky-500">
              <Search size={17} strokeWidth={2.2} />
            </div>
            <input
              type="text"
              value={search}
              onChange={(e) => {
                setSearch(e.target.value);
                setCurrentPage(1);
              }}
              placeholder="Search by engagement code, title, office, or keyword"
              className="h-9 w-full rounded-full border border-blue-200 bg-white pl-10 pr-8 text-xs text-slate-800 placeholder:text-slate-400 shadow-2xs outline-none transition focus:border-blue-400 focus:ring-1 focus:ring-blue-200"
            />
            {search && (
              <button
                onClick={() => setSearch("")}
                className="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-600 cursor-pointer"
                type="button"
              >
                <X size={14} />
              </button>
            )}
          </div>
        </div>

        {/* Table Controls Bar (Above Table) */}
        <div className="flex items-center justify-between gap-2 text-xs text-slate-600">
          <div className="text-slate-500 font-normal flex items-center gap-1.5">
            <span>Showing {totalCount > 0 ? startIndex + 1 : 0}-{endIndex} of {totalCount} engagements</span>
            {isLoading && (
              <span className="inline-flex items-center gap-1 text-[11px] text-blue-600">
                <Loader2 size={12} className="animate-spin" />
                <span>Syncing backend...</span>
              </span>
            )}
          </div>

          <div className="flex items-center gap-3">
            <button
              onClick={() => setShowFilters(!showFilters)}
              className={cn(
                "inline-flex items-center gap-1 font-medium transition cursor-pointer",
                showFilters ? "text-blue-600" : "text-slate-600 hover:text-slate-900",
              )}
              type="button"
            >
              <Filter size={13} className="text-slate-500" />
              <span>More Filters</span>
            </button>

            <div className="flex items-center gap-1.5">
              <span className="text-slate-500 font-normal">Sort by</span>
              <select
                value={sortBy}
                onChange={(e) => setSortBy(e.target.value)}
                className="rounded border border-slate-200 bg-white px-2 py-0.5 text-xs text-slate-700 outline-none hover:border-slate-300 focus:border-blue-500 cursor-pointer shadow-2xs"
              >
                <option value="code-asc">Engagement Code (A-Z)</option>
                <option value="code-desc">Engagement Code (Z-A)</option>
                <option value="title-asc">Title (A-Z)</option>
                <option value="updated-desc">Last Updated (Newest)</option>
                <option value="updated-asc">Last Updated (Oldest)</option>
              </select>
            </div>
          </div>
        </div>

        {/* Optional Expanded Filters Bar */}
        {showFilters && (
          <div className="mb-1.5 rounded-md border border-blue-100 bg-[#f5f9ff] px-2.5 py-1.5 text-xs flex flex-wrap items-center gap-2.5">
            <div className="flex items-center gap-1">
              <span className="text-slate-600 font-medium">Office:</span>
              <select
                value={officeFilter}
                onChange={(e) => {
                  setOfficeFilter(e.target.value);
                  setCurrentPage(1);
                }}
                className="rounded border border-slate-300 bg-white px-1.5 py-0.5 text-xs text-slate-700"
              >
                <option value="All">All Offices</option>
                {uniqueOffices.map((off) => (
                  <option key={off} value={off}>{off}</option>
                ))}
              </select>
            </div>

            <div className="flex items-center gap-1">
              <span className="text-slate-600 font-medium">Phase:</span>
              <select
                value={phaseFilter}
                onChange={(e) => {
                  setPhaseFilter(e.target.value);
                  setCurrentPage(1);
                }}
                className="rounded border border-slate-300 bg-white px-1.5 py-0.5 text-xs text-slate-700"
              >
                <option value="All">All Phases</option>
                <option value="Planning">Planning</option>
                <option value="Execution">Execution</option>
                <option value="Issues">Issues</option>
                <option value="AFRS">AFRS</option>
                <option value="Completion">Completion</option>
              </select>
            </div>

            <div className="flex items-center gap-1">
              <span className="text-slate-600 font-medium">Health:</span>
              <select
                value={healthFilter}
                onChange={(e) => {
                  setHealthFilter(e.target.value);
                  setCurrentPage(1);
                }}
                className="rounded border border-slate-300 bg-white px-1.5 py-0.5 text-xs text-slate-700"
              >
                <option value="All">All Health</option>
                <option value="On Track">On Track</option>
                <option value="Delayed">Delayed</option>
                <option value="At Risk">At Risk</option>
              </select>
            </div>

            {(officeFilter !== "All" ||
              phaseFilter !== "All" ||
              healthFilter !== "All" ||
              attentionFilter !== null) && (
              <button
                onClick={() => {
                  setOfficeFilter("All");
                  setPhaseFilter("All");
                  setHealthFilter("All");
                  setAttentionFilter(null);
                }}
                className="text-[11px] text-blue-600 hover:underline font-medium cursor-pointer"
                type="button"
              >
                Clear Filters
              </button>
            )}
          </div>
        )}

        {/* The Main Engagements Table */}
        <div className="overflow-hidden rounded-xl border border-[#7bb3e2] bg-white shadow-2xs">
          <div className="overflow-x-auto">
            <table className="w-full text-left text-[11.5px] border-collapse">
              <thead className="border-b border-[#7bb3e2] bg-[#dceefb] text-[#103565]">
                <tr>
                  <th className="px-3.5 py-2.5 font-bold border-r border-[#7bb3e2]/40 whitespace-nowrap">Engagement ID</th>
                  <th className="px-3.5 py-2.5 font-bold border-r border-[#7bb3e2]/40 whitespace-nowrap">Title</th>
                  <th className="px-3.5 py-2.5 font-bold border-r border-[#7bb3e2]/40 whitespace-nowrap">Office</th>
                  <th className="px-3.5 py-2.5 font-bold border-r border-[#7bb3e2]/40 whitespace-nowrap">Phase</th>
                  <th className="px-3.5 py-2.5 font-bold border-r border-[#7bb3e2]/40 whitespace-nowrap">Status</th>
                  <th className="px-3.5 py-2.5 font-bold border-r border-[#7bb3e2]/40 text-center whitespace-nowrap">Health</th>
                  <th className="px-3.5 py-2.5 font-bold border-r border-[#7bb3e2]/40 whitespace-nowrap">Team Leader</th>
                  <th className="px-3.5 py-2.5 font-bold border-r border-[#7bb3e2]/40 whitespace-nowrap">Last Updated</th>
                  <th className="px-3.5 py-2.5 font-bold text-center whitespace-nowrap">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-[#e1edf8] bg-white text-slate-700">
                {isLoading && allRecords.length === 0 ? (
                  <tr>
                    <td colSpan={9} className="py-12 text-center text-slate-500">
                      <div className="flex flex-col items-center justify-center gap-2">
                        <Loader2 size={24} className="animate-spin text-blue-600" />
                        <span className="text-xs font-medium text-slate-600">Loading live audit engagements from database...</span>
                      </div>
                    </td>
                  </tr>
                ) : displayedRecords.length === 0 ? (
                  <tr>
                    <td colSpan={9} className="py-6 text-center text-slate-500">
                      No audit engagements match your search and filter criteria.
                    </td>
                  </tr>
                ) : (
                  displayedRecords.map((record) => {
                    const titleText = record.title || record.auditArea || record.subject || "";
                    const isMenuOpen = activeMenuId === record.id;

                    return (
                      <tr
                        key={record.id}
                        className="transition-colors hover:bg-blue-50/40"
                      >
                        {/* Engagement ID */}
                        <td className="px-3 py-1.5 font-medium whitespace-nowrap border-r border-slate-100">
                          <button
                            onClick={() => handleOpenEngagement(record)}
                            className="text-[#1e60b2] hover:underline font-semibold text-left cursor-pointer"
                            type="button"
                          >
                            {record.code}
                          </button>
                        </td>

                        {/* Title */}
                        <td className="px-3 py-1.5 font-medium text-slate-800 border-r border-slate-100 max-w-xs truncate" title={titleText}>
                          {titleText}
                        </td>

                        {/* Office */}
                        <td className="px-3 py-1.5 text-slate-700 border-r border-slate-100 whitespace-nowrap">
                          {record.office}
                        </td>

                        {/* Phase */}
                        <td className="px-3 py-1.5 text-slate-700 border-r border-slate-100 whitespace-nowrap">
                          {record.phase || "Planning"}
                        </td>

                        {/* Status */}
                        <td className="px-3 py-1.5 text-slate-700 border-r border-slate-100 whitespace-nowrap">
                          {getStatusText(record.status)}
                        </td>

                        {/* Health */}
                        <td className="px-3 py-1.5 text-center border-r border-slate-100 whitespace-nowrap">
                          {getHealthBadge(record.health)}
                        </td>

                        {/* Team Leader */}
                        <td className="px-3 py-1.5 text-slate-700 border-r border-slate-100 whitespace-nowrap">
                          {record.leadAuditor || "Juan dela Cruz"}
                        </td>

                        {/* Last Updated */}
                        <td className="px-3 py-1.5 text-slate-600 text-[11px] border-r border-slate-100 whitespace-nowrap">
                          {record.lastUpdated}
                        </td>

                        {/* Actions */}
                        <td className="px-3 py-1.5 text-center whitespace-nowrap">
                          <div className="relative inline-flex items-center justify-center gap-1">
                            <button
                              onClick={() => handleOpenEngagement(record)}
                              className="rounded border border-[#7fb5ea] bg-white px-2.5 py-0.5 text-[11px] font-medium text-[#1d63b8] hover:bg-blue-50 transition cursor-pointer"
                              type="button"
                            >
                              Open
                            </button>

                            <button
                              onClick={() => setActiveMenuId(isMenuOpen ? null : record.id)}
                              className="rounded p-0.5 text-slate-500 hover:bg-slate-100 hover:text-slate-800 transition cursor-pointer"
                              title="More options"
                              type="button"
                            >
                              <MoreHorizontal size={15} />
                            </button>

                            {/* Dropdown Menu */}
                            {isMenuOpen && (
                              <div
                                className="absolute right-0 top-full mt-1 z-50 w-44 rounded-lg border border-slate-200 bg-white py-1 shadow-lg text-left text-xs"
                                onMouseLeave={() => setActiveMenuId(null)}
                              >
                                <button
                                  onClick={() => {
                                    setActiveMenuId(null);
                                    handleOpenEngagement(record);
                                  }}
                                  className="w-full px-3 py-1.5 hover:bg-blue-50 text-slate-700 flex items-center gap-2"
                                  type="button"
                                >
                                  View Details
                                </button>
                                <button
                                  onClick={() => {
                                    setActiveMenuId(null);
                                    navigateTo(`/admin/audit-engagement/${record.id}/define-scope`);
                                  }}
                                  className="w-full px-3 py-1.5 hover:bg-blue-50 text-slate-700 flex items-center gap-2"
                                  type="button"
                                >
                                  Define Scope (SCR-212)
                                </button>
                                <button
                                  onClick={() => {
                                    setActiveMenuId(null);
                                    navigateTo(`/admin/audit-engagement/${record.id}/team-assignment`);
                                  }}
                                  className="w-full px-3 py-1.5 hover:bg-blue-50 text-slate-700 flex items-center gap-2"
                                  type="button"
                                >
                                  Team Assignment (SCR-213)
                                </button>
                                <button
                                  onClick={() => {
                                    setActiveMenuId(null);
                                    navigateTo(`/admin/audit-engagement/${record.id}/prepare-order`);
                                  }}
                                  className="w-full px-3 py-1.5 hover:bg-blue-50 text-slate-700 flex items-center gap-2"
                                  type="button"
                                >
                                  Prepare Order (SCR-214)
                                </button>
                                <button
                                  onClick={() => {
                                    setActiveMenuId(null);
                                    navigateTo(`/admin/audit-engagement/${record.id}/execution`);
                                  }}
                                  className="w-full px-3 py-1.5 hover:bg-blue-50 text-slate-700 flex items-center gap-2"
                                  type="button"
                                >
                                  Execution Workspace
                                </button>
                              </div>
                            )}
                          </div>
                        </td>
                      </tr>
                    );
                  })
                )}
              </tbody>
            </table>
          </div>
        </div>

        {/* Table Pagination Bar (Below Table) */}
        <div className="mt-3.5 sm:mt-4 flex items-center justify-between text-xs text-slate-600">
          <div className="flex items-center gap-2">
            <span>Show</span>
            <select
              value={pageSize}
              onChange={(e) => {
                setPageSize(Number(e.target.value));
                setCurrentPage(1);
              }}
              className="rounded border border-slate-300 bg-white px-2 py-1 text-xs text-slate-700 outline-none cursor-pointer"
            >
              <option value={10}>10</option>
              <option value={25}>25</option>
              <option value={50}>50</option>
            </select>
            <span>entries</span>
          </div>

          <div className="flex items-center gap-1">
            <button
              onClick={() => setCurrentPage(1)}
              disabled={validCurrentPage === 1}
              className="rounded border border-slate-200 bg-white px-2 py-1 text-xs text-slate-600 hover:bg-slate-50 disabled:opacity-40 cursor-pointer disabled:cursor-not-allowed"
              title="First page"
              type="button"
            >
              &laquo;
            </button>
            {Array.from({ length: totalPages }, (_, i) => i + 1).map((pageNum) => (
              <button
                key={pageNum}
                onClick={() => setCurrentPage(pageNum)}
                className={cn(
                  "min-w-6.5 rounded px-2.5 py-1 text-xs font-medium transition cursor-pointer",
                  validCurrentPage === pageNum
                    ? "bg-[#0070d2] text-white"
                    : "border border-slate-200 bg-white text-slate-700 hover:bg-slate-50",
                )}
                type="button"
              >
                {pageNum}
              </button>
            ))}
            <button
              onClick={() => setCurrentPage((p) => Math.min(totalPages, p + 1))}
              disabled={validCurrentPage === totalPages}
              className="rounded border border-slate-200 bg-white px-2 py-1 text-xs text-slate-600 hover:bg-slate-50 disabled:opacity-40 cursor-pointer disabled:cursor-not-allowed"
              title="Next page"
              type="button"
            >
              &gt;
            </button>
            <button
              onClick={() => setCurrentPage(totalPages)}
              disabled={validCurrentPage === totalPages}
              className="rounded border border-slate-200 bg-white px-2 py-1 text-xs text-slate-600 hover:bg-slate-50 disabled:opacity-40 cursor-pointer disabled:cursor-not-allowed"
              title="Last page"
              type="button"
            >
              &raquo;
            </button>
          </div>
        </div>

        {/* Bottom-Left: Needs Attention Card */}
        <div className="mt-5 sm:mt-6 pb-2">
          <div className="w-full max-w-sm rounded-lg border border-[#7bb3e2] bg-[#f8fbff] shadow-2xs overflow-hidden">
            <div className="flex items-center justify-between border-b border-[#7bb3e2] bg-[#dceefb] px-4 py-2.5">
              <div className="flex items-center gap-2 text-[#103565]">
                <AlertTriangle size={16} className="text-[#103565]" />
                <span className="text-xs sm:text-[13px] font-bold text-[#103565]">
                  Needs Attention
                </span>
              </div>
              {isAttentionLoading && !aemsDashboard ? (
                <span className="h-3.5 w-12 animate-pulse rounded bg-sky-200/70" />
              ) : (
                <span className="text-xs text-slate-600 font-medium">
                  {needsAttention.totalCount}{" "}
                  {needsAttention.totalCount === 1 ? "item" : "items"}
                </span>
              )}
            </div>

            {isAttentionLoading && !aemsDashboard ? (
              <div className="divide-y divide-[#e5f0fb] bg-white p-0">
                {[1, 2, 3].map((idx) => (
                  <div key={idx} className="flex items-center justify-between px-4 py-3 animate-pulse">
                    <div className="flex items-center gap-3">
                      <div className="h-5 w-5 rounded-full bg-slate-200" />
                      <div className="space-y-1.5">
                        <div className="h-3 w-36 rounded bg-slate-200" />
                        <div className="h-2.5 w-28 rounded bg-slate-100" />
                      </div>
                    </div>
                    <div className="h-3.5 w-3.5 rounded bg-slate-100" />
                  </div>
                ))}
              </div>
            ) : (
              <div className="divide-y divide-[#e5f0fb] bg-white text-xs">
                {/* Item 1: Overdue / Pending Reviews */}
                <div
                  onClick={() => {
                    setOfficeFilter("All");
                    setPhaseFilter("All");
                    setHealthFilter("All");
                    setAttentionFilter((prev) => (prev === "overdue" ? null : "overdue"));
                    setCurrentPage(1);
                    showToast({
                      title: "Overdue reviews",
                      description:
                        needsAttention.overdueCount > 0
                          ? `Filtered ${needsAttention.overdueCount} item(s) requiring review or overdue attention.`
                          : "No overdue reviews or pending review queues found.",
                    });
                  }}
                  className={cn(
                    "flex items-center justify-between px-4 py-2.5 transition hover:bg-blue-50/50 cursor-pointer",
                    attentionFilter === "overdue" && "bg-blue-50",
                  )}
                >
                  <div className="flex items-center gap-3">
                    <span
                      className={cn(
                        "flex h-5 min-w-5 px-1 shrink-0 items-center justify-center rounded-full text-[10px] font-bold text-white shadow-2xs",
                        needsAttention.overdueCount > 0 ? "bg-rose-600" : "bg-emerald-600",
                      )}
                    >
                      {needsAttention.overdueCount}
                    </span>
                    <div>
                      <p className="text-[12px] font-semibold text-slate-800 leading-snug">
                        Overdue reviews &amp; procedures
                      </p>
                      <p className="text-[10.5px] text-slate-500 leading-tight mt-0.5">
                        {needsAttention.overdueCount > 0
                          ? "Items overdue or awaiting review"
                          : "No overdue reviews"}
                      </p>
                    </div>
                  </div>
                  <ChevronRight size={14} className="text-slate-400 shrink-0" />
                </div>

                {/* Item 2: Engagement due for reporting */}
                <div
                  onClick={() => {
                    setOfficeFilter("All");
                    setPhaseFilter("All");
                    setHealthFilter("All");
                    setAttentionFilter((prev) => (prev === "reporting" ? null : "reporting"));
                    setCurrentPage(1);
                    showToast({
                      title: "Due for reporting",
                      description:
                        needsAttention.reportingCount > 0
                          ? `Showing ${needsAttention.reportingCount} engagement(s) with target dates within 30 days or past due.`
                          : "No engagements are currently due within 30 days.",
                    });
                  }}
                  className={cn(
                    "flex items-center justify-between px-4 py-2.5 transition hover:bg-blue-50/50 cursor-pointer",
                    attentionFilter === "reporting" && "bg-blue-50",
                  )}
                >
                  <div className="flex items-center gap-3">
                    <span
                      className={cn(
                        "flex h-5 min-w-5 px-1 shrink-0 items-center justify-center rounded-full text-[10px] font-bold text-white shadow-2xs",
                        needsAttention.reportingCount > 0 ? "bg-rose-600" : "bg-emerald-600",
                      )}
                    >
                      {needsAttention.reportingCount}
                    </span>
                    <div>
                      <p className="text-[12px] font-semibold text-slate-800 leading-snug">
                        Engagement due for reporting
                      </p>
                      <p className="text-[10.5px] text-slate-500 leading-tight mt-0.5">
                        Target end date within 30 days
                      </p>
                    </div>
                  </div>
                  <ChevronRight size={14} className="text-slate-400 shrink-0" />
                </div>

                {/* Item 3: Incomplete planning component */}
                <div
                  onClick={() => {
                    setOfficeFilter("All");
                    setPhaseFilter("All");
                    setHealthFilter("All");
                    setAttentionFilter((prev) => (prev === "planning" ? null : "planning"));
                    setCurrentPage(1);
                    showToast({
                      title: "Incomplete planning",
                      description:
                        needsAttention.planningCount > 0
                          ? `Filtered ${needsAttention.planningCount} engagement(s) requiring scope and planning completion.`
                          : "All engagements have completed planning components.",
                    });
                  }}
                  className={cn(
                    "flex items-center justify-between px-4 py-2.5 transition hover:bg-blue-50/50 cursor-pointer",
                    attentionFilter === "planning" && "bg-blue-50",
                  )}
                >
                  <div className="flex items-center gap-3">
                    <span
                      className={cn(
                        "flex h-5 min-w-5 px-1 shrink-0 items-center justify-center rounded-full text-[10px] font-bold text-white shadow-2xs",
                        needsAttention.planningCount > 0 ? "bg-amber-500" : "bg-emerald-600",
                      )}
                    >
                      {needsAttention.planningCount}
                    </span>
                    <div>
                      <p className="text-[12px] font-semibold text-slate-800 leading-snug">
                        Incomplete planning component
                      </p>
                      <p className="text-[10.5px] text-slate-500 leading-tight mt-0.5">
                        Missing required elements
                      </p>
                    </div>
                  </div>
                  <ChevronRight size={14} className="text-slate-400 shrink-0" />
                </div>
              </div>
            )}

            <div className="flex items-center justify-between bg-white border-t border-[#edf4fc] px-4 py-2">
              {attentionFilter ? (
                <button
                  onClick={() => setAttentionFilter(null)}
                  className="text-[11px] font-medium text-slate-500 hover:text-slate-800 cursor-pointer"
                  type="button"
                >
                  Reset filter
                </button>
              ) : (
                <span />
              )}
              <button
                onClick={() => {
                  setOfficeFilter("All");
                  setPhaseFilter("All");
                  setHealthFilter("All");
                  setAttentionFilter((prev) => (prev === "all" ? null : "all"));
                  setCurrentPage(1);
                  showToast({
                    title: "Needs Attention View",
                    description:
                      needsAttention.totalCount > 0
                        ? "Showing all engagements needing attention."
                        : "All engagements are on track.",
                  });
                }}
                className="text-xs font-semibold text-[#1e60b2] hover:underline cursor-pointer"
                type="button"
              >
                {attentionFilter === "all" ? "Show all engagements" : "View all"}
              </button>
            </div>
          </div>
        </div>
      </div>
    </AdminLayout>
  );
}

export default AdminEngagementsPage;
