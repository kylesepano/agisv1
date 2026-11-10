import { useMemo, useState, useRef, useEffect } from "react";
import {
  AlertTriangle,
  ArrowLeft,
  CheckSquare,
  ChevronDown,
  FileSearch,
  FileText,
  Home,
  Menu,
  Settings,
  TrendingUp,
} from "lucide-react";
import { AdminLayout } from "../../../../layouts/admin/AdminLayout";
import { navigateTo } from "../../../../routes";
import { Button } from "../../../../components/ui/button";
import { useToast } from "../../../../components/ui/toast";
import {
  ActivityTabStub,
  AfrsTabStub,
  AuditIssuesTabStub,
  DetailsTabContent,
  ConferencesTabStub,
} from "./components";
import { EngagementLanding } from "./components/EngagementLanding";
import { getFullEngagementDetails } from "./engagement-details-data";
import type { EngagementDetailsTab, FullEngagementDetails } from "./engagement-details-types";
import { cn } from "../../../../utils/helpers";
import { preliminarySurveyService } from "../../../../services/preliminarySurveyService";

const validTabs: EngagementDetailsTab[] = [
  "details",
  "overview",
  "planning",
  "execution",
  "audit-issues",
  "afrs",
  "conferences",
  "audit-reports",
  "completion-transfer",
  "activity",
];
const newWorkspaceTabs = ["planning", "execution", "audit-reports", "completion-transfer"] as const;

export function AdminEngagementDetailsPage() {
  const { showToast } = useToast();
  const segments = window.location.pathname.split("/").filter(Boolean);
  const engagementIndex = segments.findIndex(
    (p) => p === "audit-engagement" || p === "audit-engagements"
  );
  const engagementId =
    (engagementIndex >= 0 && segments[engagementIndex + 1]) ||
    segments[2] ||
    "AEM-2026-001";
  const rawTab =
    engagementIndex >= 0 ? segments[engagementIndex + 2] : segments[3];
  const isLanding = !rawTab;
  const initialWorkspace = newWorkspaceTabs.find((tab) => tab === rawTab);
  const urlTab = (rawTab as EngagementDetailsTab) || "details";

  const activeTab: EngagementDetailsTab = validTabs.includes(urlTab)
    ? urlTab === "overview"
      ? "details"
      : urlTab
    : "details";

  const fallbackDetails = useMemo(() => {
    return getFullEngagementDetails(engagementId);
  }, [engagementId]);

  const [isLoading, setIsLoading] = useState<boolean>(true);
  const [liveDetails, setLiveDetails] = useState<FullEngagementDetails | null>(null);
  const details = liveDetails || fallbackDetails;

  useEffect(() => {
    let isMounted = true;
    setIsLoading(true);
    preliminarySurveyService
      .getEngagement(engagementId)
      .then((eng) => {
        if (!isMounted || !eng) return;
        const fallback = getFullEngagementDetails(engagementId);

        const phaseNumber =
          eng.phase === "FOUNDATION"
            ? 1
            : eng.phase === "PLANNING"
            ? 2
            : eng.phase === "CONFERENCES" || eng.phase === "ENTRY_CONFERENCE"
            ? 3
            : eng.phase === "EXECUTION" || eng.phase === "FIELDWORK"
            ? 4
            : eng.phase === "ISSUES_AFR"
            ? 5
            : eng.phase === "COMMENTS"
            ? 6
            : eng.phase === "EXIT_CONFERENCE"
            ? 7
            : eng.phase === "REPORTING"
            ? 8
            : eng.phase === "COMPLETION_TRANSFER"
            ? 9
            : 10;

        const officeName =
          eng.offices?.[0]?.name ?? eng.requestingOffice?.name ?? fallback.office;
        const officeCode = eng.offices?.[0]?.code ?? eng.requestingOffice?.code;
        const office =
          officeCode && !officeName.includes(officeCode)
            ? `${officeName} (${officeCode})`
            : officeName;

        const auditType =
          eng.auditType?.label ??
          eng.auditType?.name ??
          (typeof eng.auditType === "string" ? eng.auditType : fallback.auditType);

        const aeoNumber =
          eng.engagementOrder?.orderCode ?? eng.initialTeamPlan?.aeoReference;

        setLiveDetails({
          ...fallback,
          id: String(eng.id),
          code: eng.engagementCode || fallback.code,
          title: eng.title || fallback.title,
          auditType: auditType || fallback.auditType,
          office: office || fallback.office,
          phaseNumber,
          phase: (fallback.phase) as any,
          status: eng.administrativeStatus || eng.status || fallback.status,
          originType: eng.sourceType === "SPECIAL" ? "Unplanned" : "Planned",
          annualPlanRef: eng.sourceSnapshot?.plan?.code || fallback.annualPlanRef,
          authorityReference:
            eng.specialAuthorityReference ||
            eng.sourceSnapshot?.plan?.code ||
            fallback.authorityReference,
          directingAuthority:
            eng.specialAuthorityApprover?.name ||
            eng.sourceSnapshot?.plan?.approvedByName ||
            fallback.directingAuthority,
          dateAuthorized:
            eng.specialAuthorityReceivedDate ||
            eng.specialAuthorityDate ||
            eng.sourceSnapshot?.plan?.approvedAt ||
            fallback.dateAuthorized,
          startDate: eng.plannedStartDate || fallback.startDate,
          endDate: eng.plannedEndDate || fallback.endDate,
          lastUpdated: eng.updatedAt || fallback.lastUpdated,
          plannedManDays:
            eng.plannedPersonDays != null
              ? Number(eng.plannedPersonDays)
              : fallback.plannedManDays,
          objectives: eng.objectives || fallback.objectives,
          scopeSummary: eng.scope || fallback.scopeSummary,
          boundaries: eng.scopeBoundaries || fallback.boundaries,
          exclusions: eng.exclusions || fallback.exclusions,
          knownLimitations: eng.scopeLimitations || fallback.knownLimitations,
          auditAreas:
            Array.isArray(eng.auditAreas) && eng.auditAreas.length > 0
              ? eng.auditAreas.map((a: any) => a.name)
              : fallback.auditAreas,
          auditFocuses:
            Array.isArray(eng.auditFocuses) && eng.auditFocuses.length > 0
              ? eng.auditFocuses.map((f: any) => f.name)
              : fallback.auditFocuses,
          aeo: aeoNumber
            ? {
                aeoNumber,
                status: (eng.engagementOrder?.status === "ISSUED" ? "Issued" : "Approved") as any,
                version: `v${eng.engagementOrder?.currentVersionNumber || 1}.0`,
                issuedDate: eng.engagementOrder?.issuedAt
                  ? eng.engagementOrder.issuedAt.slice(0, 10)
                  : eng.initialTeamPlan?.aeoDate || eng.plannedStartDate || "",
                effectiveDate: eng.plannedStartDate || "",
                approver:
                  eng.initialTeamPlan?.departmentHead?.name ??
                  eng.engagementOrder?.approver?.name ??
                  "Director Manuel C. Tan, CPA, CFE",
              }
            : fallback.aeo,
        });
      })
      .catch((err) => {
        console.warn("Could not load live engagement:", err);
      })
      .finally(() => {
        if (isMounted) setIsLoading(false);
      });

    return () => {
      isMounted = false;
    };
  }, [engagementId]);

  const [moreActionsOpen, setMoreActionsOpen] = useState(false);
  const dropdownRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    function handleClickOutside(event: MouseEvent) {
      if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
        setMoreActionsOpen(false);
      }
    }
    document.addEventListener("mousedown", handleClickOutside);
    return () => document.removeEventListener("mousedown", handleClickOutside);
  }, []);

  const handleTabChange = (tabId: EngagementDetailsTab) => {
    if (tabId === "overview") {
      navigateTo(`/admin/audit-engagement/${engagementId}`);
    } else {
      navigateTo(`/admin/audit-engagement/${engagementId}/${tabId}`);
    }
  };

  const tabs: {
    id: EngagementDetailsTab;
    label: string;
    icon: React.ComponentType<{ size?: number; className?: string }>;
  }[] = [
      { id: "details", label: "Details", icon: Home },
      { id: "planning", label: "Planning", icon: FileText },
      { id: "execution", label: "Execution", icon: Settings },
      { id: "audit-issues", label: "Audit Issues", icon: AlertTriangle },
      { id: "afrs", label: "AFRs", icon: FileSearch },
      { id: "audit-reports", label: "Audit Reports", icon: TrendingUp },
      { id: "completion-transfer", label: "Completion & Transfer", icon: CheckSquare },
      { id: "activity", label: "Activity Log", icon: Menu },
    ];

  return (
    <AdminLayout>
      <div className="space-y-3 pb-10">
        {/* Breadcrumb Row */}
        <div className="flex items-center gap-1.5 text-xs text-slate-500">
          <button
            type="button"
            onClick={() => navigateTo("/admin/dashboard")}
            className="hover:text-blue-600 transition cursor-pointer"
          >
            Home
          </button>
          <span>&gt;</span>
          <button
            type="button"
            onClick={() => navigateTo("/admin/audit-engagements")}
            className="hover:text-blue-600 transition cursor-pointer"
          >
            Audit Engagements
          </button>
          <span>&gt;</span>
          <button
            type="button"
            onClick={() => navigateTo(`/admin/audit-engagement/${engagementId}`)}
            className="hover:text-blue-600 transition cursor-pointer"
          >
            Audit Engagement Details
          </button>
          {(rawTab === "planning" || initialWorkspace === "planning" || activeTab === "planning") && (
            <>
              <span>&gt;</span>
              <span className="font-semibold text-[#1877f2]">Planning</span>
            </>
          )}
        </div>

        {/* Main Title & Action Buttons Row */}
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between pt-0.5">
          <div>
            <div className="flex items-center gap-2">
              <h1 className="text-xl font-bold text-[#103565] tracking-tight">
                Audit Engagement Details
              </h1>
              <span className="text-xs font-semibold text-[#dc2626]">
                {rawTab === "planning" || initialWorkspace === "planning" || activeTab === "planning"
                  ? "[ WF-SCR-210-01 ]"
                  : "[ WF-SCR-200-03 ]"}
              </span>
            </div>
            <p className="text-xs text-slate-500 mt-0.5">
              View the authoritative details of this audit engagement and access its component workspaces.
            </p>
          </div>

          <div className="flex items-center gap-2">
            <Button
              type="button"
              variant="outline"
              onClick={() => navigateTo("/admin/audit-engagements")}
              className="border-slate-300 bg-white text-slate-700 hover:border-[#2478bf] hover:bg-[#d8ecff] hover:text-[#0b4d91] hover:shadow-md focus-visible:ring-2 focus-visible:ring-[#2478bf] transition-all duration-150 text-xs font-medium px-3 py-1.5 h-8 shadow-2xs cursor-pointer"
            >
              <ArrowLeft size={14} className="mr-1" />
              Back to AEM Workspace
            </Button>
            <Button
              type="button"
              variant="outline"
              onClick={() => {
                showToast({ title: "Edit Form", description: "Opening Engagement Edit Form...", tone: "info" });
                navigateTo("/admin/audit-engagement/create");
              }}
              className="border-slate-300 bg-white text-slate-700 hover:border-[#2478bf] hover:bg-[#d8ecff] hover:text-[#0b4d91] hover:shadow-md focus-visible:ring-2 focus-visible:ring-[#2478bf] transition-all duration-150 text-xs font-semibold px-4 py-1.5 h-8 shadow-2xs cursor-pointer"
            >
              Edit Engagement
            </Button>

            <div className="relative" ref={dropdownRef}>
              <Button
                type="button"
                onClick={() => setMoreActionsOpen(!moreActionsOpen)}
                className="bg-[#1877f2] hover:bg-[#0f5fc9] hover:shadow-md focus-visible:ring-2 focus-visible:ring-[#2478bf] text-white text-xs font-semibold px-3.5 py-1.5 h-8 shadow-2xs flex items-center gap-1.5 cursor-pointer transition-all duration-150"
              >
                <span>More Actions</span>
                <ChevronDown size={14} />
              </Button>

              {moreActionsOpen && (
                <div className="absolute right-0 top-full mt-1 w-52 rounded-md border border-slate-200 bg-white py-1 shadow-lg z-50 text-xs text-slate-700 animate-in fade-in-50 zoom-in-95">
                  <button
                    type="button"
                    onClick={() => {
                      showToast({ title: "Export Summary", description: "Exporting summary...", tone: "info" });
                      setMoreActionsOpen(false);
                    }}
                    className="w-full text-left px-3 py-2 hover:bg-[#d8ecff] hover:text-[#0b4d91] transition-colors duration-150 cursor-pointer"
                  >
                    Export Summary (PDF)
                  </button>
                  <button
                    type="button"
                    onClick={() => {
                      window.print();
                      setMoreActionsOpen(false);
                    }}
                    className="w-full text-left px-3 py-2 hover:bg-[#d8ecff] hover:text-[#0b4d91] transition-colors duration-150 cursor-pointer"
                  >
                    Print Details
                  </button>
                  <div className="my-1 border-t border-slate-100" />
                  <button
                    type="button"
                    onClick={() => {
                      navigateTo(`/admin/audit-engagement/${engagementId}/prepare-order`);
                      setMoreActionsOpen(false);
                    }}
                    className="w-full text-left px-3 py-2 hover:bg-[#d8ecff] hover:text-[#0b4d91] transition-colors duration-150 cursor-pointer"
                  >
                    View Engagement Order (AEO)
                  </button>
                  <button
                    type="button"
                    onClick={() => {
                      navigateTo(`/admin/audit-engagement/${engagementId}/define-scope`);
                      setMoreActionsOpen(false);
                    }}
                    className="w-full text-left px-3 py-2 hover:bg-[#d8ecff] hover:text-[#0b4d91] transition-colors duration-150 cursor-pointer"
                  >
                    Define Scope
                  </button>
                  <button
                    type="button"
                    onClick={() => {
                      navigateTo(`/admin/audit-engagement/${engagementId}/team-assignment`);
                      setMoreActionsOpen(false);
                    }}
                    className="w-full text-left px-3 py-2 hover:bg-[#d8ecff] hover:text-[#0b4d91] transition-colors duration-150 cursor-pointer"
                  >
                    Team Assignment
                  </button>
                </div>
              )}
            </div>
          </div>
        </div>

        {isLanding || initialWorkspace ? (
          <EngagementLanding
            key={rawTab ?? "landing"}
            details={details}
            engagementId={engagementId}
            initialWorkspace={initialWorkspace}
            isLoading={isLoading}
          />
        ) : (
          <>
        {/* Identity & Metadata Card */}
        {isLoading ? (
          <div className="rounded-md border border-[#7bb3e2]/60 bg-white p-4 sm:p-5 shadow-2xs animate-pulse">
            <div className="grid grid-cols-1 gap-4 lg:grid-cols-12 lg:items-center">
              <div className="lg:col-span-4 space-y-2">
                <div className="h-5 w-36 rounded bg-blue-200/70" />
                <div className="h-4 w-48 rounded bg-slate-300/70" />
                <div className="h-3.5 w-32 rounded bg-slate-200/80" />
              </div>
              <div className="lg:col-span-4 lg:border-l lg:border-slate-200 lg:pl-8 space-y-2">
                <div className="h-3.5 w-40 rounded bg-slate-200/80" />
                <div className="h-3.5 w-32 rounded bg-slate-200/80" />
                <div className="h-3.5 w-44 rounded bg-slate-200/80" />
              </div>
              <div className="lg:col-span-4 lg:border-l lg:border-slate-200 lg:pl-8 space-y-2.5">
                <div className="h-4 w-36 rounded bg-amber-100/80" />
                <div className="h-4 w-36 rounded bg-sky-100/80" />
              </div>
            </div>
          </div>
        ) : (
          <div className="rounded-md border border-[#7bb3e2] bg-white p-4 sm:p-5 shadow-2xs">
            <div className="grid grid-cols-1 gap-4 lg:grid-cols-12 lg:items-center">
              {/* Left Identity Column */}
              <div className="lg:col-span-4 space-y-1">
                <h2 className="text-lg font-bold text-[#103565] tracking-tight">
                  {details.code || "AEM-2026-001"}
                </h2>
                <p className="text-sm font-bold text-slate-800">
                  {details.title || "Procurement Process Audit"}
                </p>
                <p className="text-xs text-slate-500">
                  {details.office
                    ? details.office.includes("(")
                      ? details.office
                      : `${details.office} (CGSO)`
                    : "City General Services Office (CGSO)"}
                </p>
              </div>

              {/* Center Metadata Column */}
              <div className="lg:col-span-4 lg:border-l lg:border-slate-200 lg:pl-8 space-y-2 text-xs">
                <div className="flex items-center">
                  <span className="w-28 text-slate-500 font-medium">Audit Type:</span>
                  <span className="font-semibold text-slate-800">
                    {details.auditType
                      ? details.auditType.includes("Audit")
                        ? details.auditType
                        : `${details.auditType} Audit`
                      : "Operations Audit"}
                  </span>
                </div>
                <div className="flex items-center">
                  <span className="w-28 text-slate-500 font-medium">Audit Year:</span>
                  <span className="font-semibold text-slate-800">
                    {details.startDate?.split("-")[0] || "2026"}
                  </span>
                </div>
                <div className="flex items-center">
                  <span className="w-28 text-slate-500 font-medium">Period Covered:</span>
                  <span className="font-semibold text-slate-800">
                    Jan 1, 2025 - Dec 31, 2025
                  </span>
                </div>
              </div>

              {/* Right Status Badges Column */}
              <div className="lg:col-span-4 lg:border-l lg:border-slate-200 lg:pl-8 space-y-2.5 text-xs">
                <div className="flex items-center justify-between sm:justify-start sm:gap-6">
                  <span className="text-slate-500 font-medium">Current Phase</span>
                  <span className="inline-flex items-center justify-center rounded-full bg-[#fef3c7] px-3.5 py-0.5 text-xs font-semibold text-[#92400e] border border-[#fde68a] min-w-[84px]">
                    {details.phase
                      ? details.phase.replace(/^[0-9]+\.\s*/, "").split(" or ")[0].split(" and ")[0]
                      : "Planning"}
                  </span>
                </div>
                <div className="flex items-center justify-between sm:justify-start sm:gap-6">
                  <span className="text-slate-500 font-medium">Engagement Status</span>
                  <span className="inline-flex items-center justify-center rounded-full bg-[#e0f2fe] px-3.5 py-0.5 text-xs font-semibold text-[#0284c7] border border-[#bae6fd] min-w-[84px]">
                    {details.status === "Active" ? "In Progress" : details.status || "In Progress"}
                  </span>
                </div>
              </div>
            </div>
          </div>
        )}

        {/* Tabbed Workspace Section with Integrated Folder-Style Tabs */}
        <div className="relative pt-1">
          {/* Top Tabs Header Strip sitting directly on top of the container */}
          <div className="flex items-end overflow-x-auto select-none pl-0 scrollbar-none">
            {tabs.map((t, idx) => {
              const Icon = t.icon;
              const isActive = activeTab === t.id;

              if (isActive) {
                return (
                  <button
                    key={t.id}
                    type="button"
                    onClick={() => handleTabChange(t.id)}
                    className={cn(
                      "relative z-10 -mb-[1px] flex items-center gap-2 px-5 py-2.5 text-xs font-bold text-[#1d63b8] bg-[#edf6fd] border-t border-l border-r border-[#7bb3e2] cursor-pointer whitespace-nowrap",
                      idx === 0 ? "rounded-tl-md" : "rounded-t-md",
                      "rounded-tr-md"
                    )}
                  >
                    <Icon size={15} className="text-[#1d63b8]" />
                    <span>{t.label}</span>
                    {/* Active tab bottom indicator teal bar */}
                    <span className="absolute -bottom-[1px] left-2 right-2 h-[3.5px] bg-[#00d89c] rounded-full z-20" />
                    {/* Seamless mask to erase container top border beneath active tab */}
                    <span className="absolute -bottom-[1px] left-0 right-0 h-[2px] bg-[#edf6fd] z-10" />
                  </button>
                );
              }

              return (
                <button
                  key={t.id}
                  type="button"
                  onClick={() => handleTabChange(t.id)}
                  className="relative z-0 flex items-center gap-2 px-4 py-2.5 text-xs font-semibold text-[#1e293b] hover:text-[#1d63b8] hover:bg-slate-100/50 transition cursor-pointer whitespace-nowrap rounded-t-md"
                >
                  <Icon size={15} className="text-[#0284c7]" />
                  <span>{t.label}</span>
                </button>
              );
            })}
          </div>

          {/* Master Tabbed Workspace Container Body */}
          <div
            className={cn(
              "border border-[#7bb3e2] bg-[#edf6fd] p-3.5 sm:p-4 shadow-xs",
              activeTab === "details"
                ? "rounded-b-md rounded-tr-md"
                : "rounded-b-md rounded-t-md"
            )}
          >
            {activeTab === "details" && <DetailsTabContent details={details} />}
            {activeTab === "audit-issues" && <AuditIssuesTabStub details={details} />}
            {activeTab === "afrs" && <AfrsTabStub details={details} />}
            {activeTab === "conferences" && <ConferencesTabStub details={details} />}
            {activeTab === "activity" && <ActivityTabStub details={details} />}
          </div>
        </div>
          </>
        )}
      </div>
    </AdminLayout>
  );
}

export default AdminEngagementDetailsPage;
