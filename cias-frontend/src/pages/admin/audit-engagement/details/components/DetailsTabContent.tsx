import { Calendar, ExternalLink, Folder, Info, Target } from "lucide-react";
import type { FullEngagementDetails } from "../engagement-details-types";
import { navigateTo } from "../../../../../routes";

type DetailsTabContentProps = {
  details: FullEngagementDetails;
};

export function DetailsTabContent({ details }: DetailsTabContentProps) {
  const scopeAreas =
    details.auditAreas &&
      details.auditAreas.length > 0 &&
      !details.auditAreas.includes("General Audit Area")
      ? details.auditAreas
      : [
        "Procurement Planning",
        "Procurement Processing",
        "Bidding and Award Support",
        "Delivery, Inspection and Acceptance",
      ];

  return (
    <div className="w-full lg:w-[80%] space-y-3.5">
      {/* Row 1: Engagement Source & Initial Schedule (2-col grid) */}
      <div className="grid grid-cols-1 gap-3.5 md:grid-cols-2">
        {/* Card 1: Engagement Source */}
        <div className="rounded-md border border-[#7bb3e2] bg-white shadow-2xs overflow-hidden">
          <div className="flex items-center gap-2 border-b border-[#7bb3e2] bg-[#dceefb] px-4 py-2 text-xs font-bold text-[#103565]">
            <Info size={15} className="text-[#103565]" />
            <span>Engagement Source</span>
          </div>
          <div className="p-4 space-y-2.5 text-xs bg-white">
            <div className="flex items-center">
              <span className="w-36 shrink-0 text-slate-600 font-medium">
                Engagement Source
              </span>
              <span className="text-slate-800 font-medium">
                {details.originType === "Planned"
                  ? "Internal Audit Planning (IAP)"
                  : "Authorized Unplanned Engagement"}
              </span>
            </div>

            <div className="flex items-center">
              <span className="w-36 shrink-0 text-slate-600 font-medium">
                Plan Reference
              </span>
              <div>
                <a
                  href="/admin/annual-audit-planning/annual-audit-plan"
                  onClick={(e) => {
                    e.preventDefault();
                    navigateTo("/admin/annual-audit-planning/annual-audit-plan");
                  }}
                  className="inline-flex items-center gap-1 font-semibold text-blue-600 hover:text-blue-700 hover:underline"
                >
                  <span>{details.annualPlanRef || "AAP-2026-01"}</span>
                  <ExternalLink size={12} className="text-blue-600" />
                </a>
              </div>
            </div>

            <div className="flex items-center">
              <span className="w-36 shrink-0 text-slate-600 font-medium">
                Approved By
              </span>
              <span className="text-slate-800 font-medium">
                {details.directingAuthority || "City Mayor"}
              </span>
            </div>

            <div className="flex items-center">
              <span className="w-36 shrink-0 text-slate-600 font-medium">
                Approval Date
              </span>
              <span className="text-slate-800 font-medium">
                Nov 15, 2025
              </span>
            </div>
          </div>
        </div>

        {/* Card 2: Initial Schedule */}
        <div className="rounded-md border border-[#7bb3e2] bg-white shadow-2xs overflow-hidden">
          <div className="flex items-center gap-2 border-b border-[#7bb3e2] bg-[#dceefb] px-4 py-2 text-xs font-bold text-[#103565]">
            <Calendar size={15} className="text-[#103565]" />
            <span>Initial Schedule</span>
          </div>
          <div className="p-4 space-y-2.5 text-xs bg-white">
            <div className="flex items-center">
              <span className="w-36 shrink-0 text-slate-600 font-medium">
                Start Date
              </span>
              <span className="text-slate-800 font-medium">
                {details.startDate
                  ? new Date(details.startDate).toLocaleDateString("en-US", {
                    month: "short",
                    day: "numeric",
                    year: "numeric",
                  })
                  : "Jan 15, 2026"}
              </span>
            </div>

            <div className="flex items-center">
              <span className="w-36 shrink-0 text-slate-600 font-medium">
                End Date
              </span>
              <span className="text-slate-800 font-medium">
                {details.endDate
                  ? new Date(details.endDate).toLocaleDateString("en-US", {
                    month: "short",
                    day: "numeric",
                    year: "numeric",
                  })
                  : "Mar 31, 2026"}
              </span>
            </div>

            <div className="flex items-center">
              <span className="w-36 shrink-0 text-slate-600 font-medium">
                Planned Duration
              </span>
              <span className="text-slate-800 font-medium">
                {details.plannedManDays ? `${details.plannedManDays} days` : "76 days"}
              </span>
            </div>
          </div>
        </div>
      </div>

      {/* Row 2: Initial Scope (full-width) */}
      <div className="rounded-md border border-[#7bb3e2] bg-white shadow-2xs overflow-hidden">
        <div className="flex items-center gap-2 border-b border-[#7bb3e2] bg-[#dceefb] px-4 py-2 text-xs font-bold text-[#103565]">
          <Target size={15} className="text-[#103565]" />
          <span>Initial Scope</span>
        </div>
        <div className="p-4 space-y-3 text-xs bg-white">
          <div className="flex items-start">
            <span className="w-36 shrink-0 text-slate-600 font-medium pt-1">
              Audit Area(s)
            </span>
            <div className="flex flex-wrap gap-2">
              {scopeAreas.map((area, idx) => (
                <span
                  key={idx}
                  className="inline-flex items-center rounded border border-[#bfd8ef] bg-[#eef6fc] px-2.5 py-0.5 text-xs text-slate-700 shadow-2xs font-medium"
                >
                  {area}
                </span>
              ))}
            </div>
          </div>

          <div className="flex items-start pt-1">
            <span className="w-36 shrink-0 text-slate-600 font-medium pt-2">
              Initial Objective
            </span>
            <div className="flex-1 rounded border border-[#7bb3e2] bg-[#f9fcff] px-3 py-2 text-xs text-slate-800 shadow-2xs leading-relaxed">
              {details.objectives ||
                "Assess the adequacy, efficiency, and compliance of the procurement process in CGSO in accordance with applicable laws, rules, and regulations."}
            </div>
          </div>
        </div>
      </div>

      {/* Row 3: Record Information (full-width) */}
      <div className="rounded-md border border-[#7bb3e2] bg-white shadow-2xs overflow-hidden">
        <div className="flex items-center gap-2 border-b border-[#7bb3e2] bg-[#dceefb] px-4 py-2 text-xs font-bold text-[#103565]">
          <Folder size={15} className="text-[#103565]" />
          <span>Record Information</span>
        </div>
        <div className="p-4 text-xs bg-white space-y-2.5">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div className="flex items-center">
              <span className="w-36 shrink-0 text-slate-600 font-medium">Created By</span>
              <span className="text-slate-800 font-medium">
                {details.team?.[0]?.name || "Juan dela Cruz"}
              </span>
            </div>
            <div className="flex items-center">
              <span className="w-36 shrink-0 text-slate-600 font-medium">Date Created</span>
              <span className="text-slate-800 font-medium">Jan 10, 2026</span>
            </div>
          </div>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div className="flex items-center">
              <span className="w-36 shrink-0 text-slate-600 font-medium">Last Updated</span>
              <span className="text-slate-800 font-medium">
                {details.lastUpdated || "Jan 27, 2026"}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
