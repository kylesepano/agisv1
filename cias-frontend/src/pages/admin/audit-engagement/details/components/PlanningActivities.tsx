import { useState } from "react";
import { ArrowLeft, Clock, Construction } from "lucide-react";
import { navigateTo } from "../../../../../routes";
import { useToast } from "../../../../../components/ui/toast";
import type { FullEngagementDetails } from "../engagement-details-types";
import { PlanningWorkspace } from "./planning";

type PlanningActivitiesProps = {
  details: FullEngagementDetails;
  engagementId: string;
};

type SubItem = {
  id: string;
  title: string;
  target: string;
};

type PlanningActivity = {
  id: number;
  title: string;
  description: string;
  target: string;
  subItems: SubItem[];
};

const activities: PlanningActivity[] = [
  {
    id: 1,
    title: "Preliminary Survey",
    description:
      "Obtain sufficient preliminary understanding of the activity and identify relevant risks, controls, information gaps, and matters requiring further engagement planning.",
    target: "workspace",
    subItems: [
      { id: "1.1", title: "1.1 Preliminary Meeting", target: "preliminary-meeting" },
      { id: "1.2", title: "1.2 Understanding of the Auditee", target: "understanding-auditee" },
      {
        id: "1.3",
        title: "1.3 Understanding of the Program, Project, System or Process under Audit",
        target: "understanding-process",
      },
      { id: "1.4", title: "1.4 Initial Assessment of the ICS", target: "initial-assessment-ics" },
    ],
  },
  {
    id: 2,
    title: "Determination of Audit Objectives, Scope, Criteria & Evidence",
    description:
      "Establish specific engagement objectives, boundaries of the audit scope, applicable evaluation criteria (statutory, regulatory, administrative), and required evidence types.",
    target: "define-scope",
    subItems: [
      { id: "2.1", title: "2.1 Determination of Audit Objectives", target: "define-scope" },
      { id: "2.2", title: "2.2 Determination of Audit Scope & Boundaries", target: "define-scope" },
      { id: "2.3", title: "2.3 Determination of Audit Criteria (Laws, Rules, Policies)", target: "define-scope" },
      { id: "2.4", title: "2.4 Determination of Required Audit Evidence Types", target: "define-scope" },
    ],
  },
  {
    id: 3,
    title: "Determination of Audit Methodology",
    description:
      "Define audit procedures, testing techniques, data analytics, sampling approaches, and verification methods to evaluate internal controls and operational compliance.",
    target: "programs",
    subItems: [
      { id: "3.1", title: "3.1 Determination of Audit Testing & Verification Approaches", target: "programs" },
      { id: "3.2", title: "3.2 Preparation of Audit Programs & Detailed Procedures", target: "programs" },
      { id: "3.3", title: "3.3 Determination of Audit Sampling Methodologies", target: "programs" },
    ],
  },
  {
    id: 4,
    title: "Determination of Required Resources and Target Milestones",
    description:
      "Allocate qualified audit personnel, estimate required person-hours, establish key engagement milestone target dates, and assign specific audit procedures.",
    target: "team-assignment",
    subItems: [
      { id: "4.1", title: "4.1 Audit Team Resource Allocation & Person-Hour Budget", target: "team-assignment" },
      { id: "4.2", title: "4.2 Determination of Engagement Target Milestones & Timeline", target: "team-assignment" },
      { id: "4.3", title: "4.3 Determination of Logistics & Technical Facilities", target: "team-assignment" },
    ],
  },
  {
    id: 5,
    title: "Development of the Audit Engagement Plan",
    description:
      "Consolidate background, risks, objectives, scope, methodology, resources, and schedule into the formal authoritative Audit Engagement Plan (AEP).",
    target: "aep",
    subItems: [
      { id: "5.1", title: "5.1 Formulation of Executive Engagement Strategy", target: "aep" },
      { id: "5.2", title: "5.2 Consolidation of Audit Engagement Plan (AEP)", target: "aep" },
    ],
  },
  {
    id: 6,
    title: "Determination of Audit Engagement KPIs",
    description:
      "Establish measurable performance indicators, milestone delivery targets, budget adherence, and quality benchmarks for the engagement.",
    target: "aep",
    subItems: [
      { id: "6.1", title: "6.1 Definition of Engagement Timeline & Milestone KPIs", target: "aep" },
      { id: "6.2", title: "6.2 Definition of Deliverables & Output Quality KPIs", target: "aep" },
    ],
  },
  {
    id: 7,
    title: "Review and Approval of AEP and KPIs",
    description:
      "Submit the completed Audit Engagement Plan and KPIs for supervisory review, revisions, and department head approval prior to fieldwork.",
    target: "aep",
    subItems: [
      { id: "7.1", title: "7.1 Supervisory Review of Draft AEP and KPIs", target: "aep" },
      { id: "7.2", title: "7.2 Department Head Final Approval & Clearance", target: "aep" },
    ],
  },
  {
    id: 8,
    title: "Preparation for Entrance Conference",
    description:
      "Coordinate conference schedule, prepare presentation materials, distribute the engagement announcement letter, and brief auditee management on audit objectives and protocols.",
    target: "entry-conference",
    subItems: [
      { id: "8.1", title: "8.1 Stakeholder Coordination & Schedule Confirmation", target: "entry-conference" },
      { id: "8.2", title: "8.2 Distribution of Engagement Announcement Letter", target: "entry-conference" },
      { id: "8.3", title: "8.3 Preparation of Entrance Conference Presentation Materials", target: "entry-conference" },
    ],
  },
];

export function PlanningActivities({ details, engagementId }: PlanningActivitiesProps) {
  const { showToast } = useToast();
  const [selectedIndex, setSelectedIndex] = useState<number | null>(null);
  const [workspaceOpen, setWorkspaceOpen] = useState(false);
  const selectedActivity = selectedIndex !== null ? activities[selectedIndex] : null;

  const openActivity = (target: string) => {
    if (target === "preliminary-meeting") {
      navigateTo(
        `/admin/audit-engagement/${engagementId}/planning/preliminary-survey/preliminary-meeting`
      );
    } else if (target === "understanding-auditee") {
      navigateTo(
        `/admin/audit-engagement/${engagementId}/planning/preliminary-survey/understanding-auditee`
      );
    } else if (target === "understanding-process") {
      navigateTo(
        `/admin/audit-engagement/${engagementId}/planning/preliminary-survey/understanding-process`
      );
    } else if (target === "initial-assessment-ics") {
      navigateTo(
        `/admin/audit-engagement/${engagementId}/planning/preliminary-survey/initial-assessment-ics`
      );
    } else if (target === "aep") {
      navigateTo(`/admin/audit-engagement/${engagementId}/planning/aep`);
    } else if (target === "programs") {
      navigateTo(`/admin/audit-engagement/${engagementId}/planning/programs`);
    } else if (target === "define-scope") {
      navigateTo(`/admin/audit-engagement/${engagementId}/define-scope`);
    } else if (target === "team-assignment") {
      navigateTo(`/admin/audit-engagement/${engagementId}/team-assignment`);
    } else if (target === "entry-conference") {
      navigateTo(`/admin/audit-engagement/${engagementId}/conference/entry`);
    } else {
      setWorkspaceOpen(true);
    }
  };

  const handleSubItemClick = (target: string, title: string) => {
    if (target === "workspace") {
      setWorkspaceOpen(true);
      showToast({
        title,
        description: `Opening workspace for ${title}...`,
        tone: "info",
      });
    } else {
      openActivity(target);
      showToast({
        title,
        description: `Navigating to ${title}...`,
        tone: "info",
      });
    }
  };

  return (
    <div className="overflow-hidden rounded-md border border-[#9fc2db] bg-white font-sans text-[#1d2e68]">
      {/* Table-style Header Bar */}
      <div className="flex items-center gap-2.5 border-b border-[#9fc2db] bg-[#dcebf7] px-4 py-2.5 text-[14px] font-bold leading-5 text-[#1d2e68]">
        <div className="flex h-5 w-5 items-center justify-center rounded border border-[#2b4c7e] bg-white text-[#2b4c7e]">
          <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.8" className="h-3.5 w-3.5">
            <rect x="2" y="2" width="12" height="12" rx="1.5" />
            <line x1="2" y1="6" x2="14" y2="6" />
            <line x1="6" y1="6" x2="6" y2="14" />
          </svg>
        </div>
        <h2>Planning Activities</h2>
      </div>

      {workspaceOpen ? (
        <div className="p-4 bg-[#f4faff]">
          <button
            type="button"
            onClick={() => setWorkspaceOpen(false)}
            className="mb-4 inline-flex cursor-pointer items-center gap-2 rounded px-1 text-xs font-semibold text-[#173575] transition-colors duration-150 hover:bg-[#d8ecff] hover:text-[#0b4d91] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2478bf]"
          >
            <ArrowLeft size={15} /> Back to Planning Activities
          </button>
          <PlanningWorkspace details={details} />
        </div>
      ) : (
        <div className="grid min-h-[460px] md:grid-cols-[minmax(320px,420px)_minmax(0,1fr)]">
          {/* Left Navigation List */}
          <nav aria-label="Planning activities" className="border-b border-[#9fc2db] bg-white py-2 md:border-b-0 md:border-r">
            {activities.map((activity, index) => {
              const isSelected = selectedIndex === index;
              return (
                <button
                  key={activity.id}
                  type="button"
                  aria-pressed={isSelected}
                  onClick={() => setSelectedIndex(isSelected ? null : index)}
                  className={
                    isSelected
                      ? "flex w-full cursor-pointer items-center gap-3 border-r-[6px] border-[#1188ed] bg-[#bfe2ff] px-4 py-2.5 text-left text-[13px] font-semibold leading-5 text-[#1d2e68] transition-colors duration-150 hover:bg-[#add8fa] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#2478bf]"
                      : "flex w-full cursor-pointer items-center gap-3 px-4 py-2.5 text-left text-[13px] leading-5 text-[#1d2e68] transition-colors duration-150 hover:bg-[#d8ecff] hover:text-[#0b4d91] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#2478bf]"
                  }
                >
                  <span className="w-4 shrink-0 font-bold text-slate-700">{activity.id}</span>
                  <span className="min-w-0 flex-1">{activity.title}</span>
                  {activity.id > 1 && (
                    <span className="shrink-0 rounded bg-amber-50 px-1.5 py-0.5 text-[10px] font-medium text-amber-700 ring-1 ring-inset ring-amber-600/20">
                      In Dev
                    </span>
                  )}
                </button>
              );
            })}
          </nav>

          {/* Right Panel: Unselected Empty State OR Selected Activity Details */}
          {selectedActivity ? (
            <div className="min-w-0 bg-white px-6 pb-8 pt-5">
              {/* Header with blue icon, title, and subtitle */}
              <div className="flex items-start gap-3 text-[#1d2e68]">
                <div className="flex h-7 w-7 shrink-0 items-center justify-center rounded border-[1.5px] border-[#0a75b8] bg-white text-[#0a75b8] shadow-2xs mt-0.5">
                  <svg
                    viewBox="0 0 20 20"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="2"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    className="h-4 w-4"
                  >
                    <rect x="2" y="2" width="16" height="16" rx="2" strokeWidth="1.5" />
                    <circle cx="6" cy="6.5" r="1" fill="currentColor" stroke="none" />
                    <line x1="9" y1="6.5" x2="14" y2="6.5" />
                    <circle cx="6" cy="10" r="1" fill="currentColor" stroke="none" />
                    <line x1="9" y1="10" x2="14" y2="10" />
                    <circle cx="6" cy="13.5" r="1" fill="currentColor" stroke="none" />
                    <line x1="9" y1="13.5" x2="14" y2="13.5" />
                  </svg>
                </div>
                <div className="min-w-0 flex-1">
                  <div className="flex flex-wrap items-center gap-2">
                    <h3 className="text-[16px] font-bold leading-5 text-[#1d2e68]">
                      {selectedActivity.title}
                    </h3>
                    {selectedActivity.id > 1 && (
                      <span className="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-700 ring-1 ring-inset ring-amber-600/20">
                        <Clock className="h-3 w-3 text-amber-600" />
                        Under Development
                      </span>
                    )}
                  </div>
                  <p className="mt-1 text-[12px] leading-[18px] text-[#475569]">
                    {selectedActivity.description}
                  </p>
                </div>
              </div>

              {selectedActivity.id === 1 ? (
                /* Box containing the sub-items matching the screenshot for Step 1 */
                <div className="mt-6 rounded-md border border-[#9fc2db] bg-[#f8fcff] p-6 sm:p-8">
                  <div className="mx-auto max-w-2xl space-y-3">
                    {selectedActivity.subItems.map((subItem) => (
                      <button
                        key={subItem.id}
                        type="button"
                        onClick={() => handleSubItemClick(subItem.target, subItem.title)}
                        className="flex w-full cursor-pointer items-center rounded-md border border-[#7bb3e2] bg-[#e7f3ff] px-5 py-3 text-left text-[13px] font-semibold text-[#1d2e68] shadow-2xs transition-all duration-150 hover:border-[#2478bf] hover:bg-[#d5eaff] hover:text-[#0b4d91] hover:shadow-xs focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2478bf]"
                      >
                        <span>{subItem.title}</span>
                      </button>
                    ))}
                  </div>
                </div>
              ) : (
                /* Under Development Notice for Steps 2 to 8 matching PlanningActivities style */
                <div className="mt-6 flex flex-col items-center justify-center rounded-md border border-[#9fc2db] bg-[#f8fcff] px-6 py-10 text-center">
                  <div className="flex h-[76px] w-[76px] items-center justify-center rounded-2xl border-2 border-[#5464a0] bg-[#f4f7fc] text-[#5464a0] shadow-2xs">
                    <Construction className="h-9 w-9" strokeWidth={1.8} />
                  </div>
                  <h4 className="mt-6 text-[18px] font-bold text-[#1d2e68]">
                    Step {selectedActivity.id} is Under Active Development
                  </h4>
                  <p className="mt-2.5 max-w-[440px] text-[13px] leading-relaxed text-slate-600">
                    The interactive workflows and audit forms for{" "}
                    <span className="font-semibold text-[#1d2e68]">
                      &ldquo;{selectedActivity.title}&rdquo;
                    </span>{" "}
                    are currently in development.
                  </p>
                  <p className="mt-3.5 max-w-[420px] text-[12px] leading-relaxed text-slate-500">
                    Use Step 1 (Preliminary Survey) to continue your current engagement planning work.
                  </p>

                  {/*
                    ORIGINAL SUB-ITEMS (COMMENTED OUT - DO NOT DELETE):
                    <div className="mt-6 rounded-md border border-[#9fc2db] bg-[#f8fcff] p-6 sm:p-8">
                      <div className="mx-auto max-w-2xl space-y-3">
                        {selectedActivity.subItems.map((subItem) => (
                          <button
                            key={subItem.id}
                            type="button"
                            onClick={() => handleSubItemClick(subItem.target, subItem.title)}
                            className="flex w-full cursor-pointer items-center rounded-md border border-[#7bb3e2] bg-[#e7f3ff] px-5 py-3 text-left text-[13px] font-semibold text-[#1d2e68] shadow-2xs transition-all duration-150 hover:border-[#2478bf] hover:bg-[#d5eaff] hover:text-[#0b4d91] hover:shadow-xs focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2478bf]"
                          >
                            <span>{subItem.title}</span>
                          </button>
                        ))}
                      </div>
                    </div>
                  */}
                </div>
              )}
            </div>
          ) : (
            /* Default Unselected State matching user attached image */
            <div className="flex min-h-[460px] flex-col items-center justify-center bg-white px-6 py-12 text-center">
              <div className="flex h-[76px] w-[76px] items-center justify-center rounded-2xl border-2 border-[#5464a0] bg-[#f4f7fc] text-[#5464a0] shadow-2xs">
                <svg
                  viewBox="0 0 28 28"
                  fill="none"
                  stroke="#5464a0"
                  strokeWidth="2.3"
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  className="h-11 w-11"
                >
                  <circle cx="6" cy="6" r="1.4" fill="#5464a0" stroke="none" />
                  <line x1="12" y1="6" x2="23" y2="6" />
                  <circle cx="6" cy="11.5" r="1.4" fill="#5464a0" stroke="none" />
                  <line x1="12" y1="11.5" x2="23" y2="11.5" />
                  <circle cx="6" cy="17" r="1.4" fill="#5464a0" stroke="none" />
                  <line x1="12" y1="17" x2="23" y2="17" />
                  <circle cx="6" cy="22.5" r="1.4" fill="#5464a0" stroke="none" />
                  <line x1="12" y1="22.5" x2="23" y2="22.5" />
                </svg>
              </div>

              <h3 className="mt-6 text-[18px] font-bold text-[#1d2e68]">
                Audit Engagement Planning
              </h3>

              <p className="mt-2.5 max-w-[440px] text-[13px] leading-relaxed text-slate-600">
                Select a planning activity from the list on the left to view its purpose,
                progress, and available actions.
              </p>

              <p className="mt-3.5 max-w-[420px] text-[12px] leading-relaxed text-slate-500">
                Begin with Preliminary Survey or select another activity to continue your
                engagement planning work.
              </p>
            </div>
          )}
        </div>
      )}
    </div>
  );
}
