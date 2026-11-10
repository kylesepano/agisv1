import { useState } from "react";
import {
  ArrowRight,
  CheckCircle2,
  ChevronRight,
  Circle,
  CircleDot,
  FileText,
} from "lucide-react";
import { navigateTo } from "../../../../../routes";
import type { FullEngagementDetails } from "../engagement-details-types";
import { PlanningActivities } from "./PlanningActivities";
import { CompletionIcon, ExecutionIcon, PlanningIcon, ReportingIcon } from "./WorkspaceIcons";

type EngagementLandingProps = {
  details: FullEngagementDetails;
  engagementId: string;
  initialWorkspace?: WorkspaceId;
  isLoading?: boolean;
};

const workspaces = [
  { id: "planning", label: "Planning", icon: PlanningIcon },
  { id: "execution", label: "Execution", icon: ExecutionIcon },
  { id: "audit-reports", label: "Reporting", icon: ReportingIcon },
  { id: "completion-transfer", label: "Completion & Transfer", icon: CompletionIcon },
] as const;

type WorkspaceId = (typeof workspaces)[number]["id"];

export function EngagementLanding({ details, engagementId, initialWorkspace, isLoading }: EngagementLandingProps) {
  const [selectedWorkspace, setSelectedWorkspace] = useState<WorkspaceId | null>(initialWorkspace ?? null);
  const basePath = `/admin/audit-engagement/${engagementId}`;
  const selectedStage = workspaces.find((workspace) => workspace.id === selectedWorkspace);
  const SelectedIcon = selectedStage?.icon ?? Circle;
  const auditYear = Number(details.startDate?.slice(0, 4)) || new Date().getFullYear();
  const periodYear = auditYear - 1;
  const authorizationDone = details.phaseNumber > 1;
  const progress = [
    { label: "Authorization", status: authorizationDone ? "Done" : "In Progress", icon: authorizationDone ? CheckCircle2 : CircleDot },
    { label: "Planning", status: details.phaseNumber > 2 ? "Done" : details.phaseNumber === 2 ? "In Progress" : "Not Started", icon: details.phaseNumber > 2 ? CheckCircle2 : details.phaseNumber === 2 ? CircleDot : Circle },
    { label: "Execution", status: details.phaseNumber > 4 ? "Done" : details.phaseNumber >= 4 ? "In Progress" : "Not Started", icon: details.phaseNumber > 4 ? CheckCircle2 : details.phaseNumber >= 4 ? CircleDot : Circle },
    { label: "Reporting", status: details.phaseNumber > 8 ? "Done" : details.phaseNumber === 8 ? "In Progress" : "Not Started", icon: details.phaseNumber > 8 ? CheckCircle2 : details.phaseNumber === 8 ? CircleDot : Circle },
    { label: "Completion & Transfer", status: details.phaseNumber > 9 ? "Done" : details.phaseNumber === 9 ? "In Progress" : "Not Started", icon: details.phaseNumber > 9 ? CheckCircle2 : details.phaseNumber === 9 ? CircleDot : Circle },
  ];

  return (
    <div className="space-y-5">
      {isLoading ? (
        <div className="grid gap-3.5 lg:grid-cols-[minmax(0,1fr)_260px] animate-pulse">
          {/* Left Header Card Skeleton */}
          <div className="relative rounded-md border border-[#4387bd]/40 bg-[#f5fbff] px-5 py-6 pb-9 shadow-sm lg:pb-6">
            <div className="grid gap-5 lg:grid-cols-[minmax(230px,1.1fr)_minmax(310px,1.5fr)_minmax(210px,0.9fr)] lg:items-center">
              {/* Identity Column */}
              <div className="flex min-w-0 items-start gap-4 lg:border-r lg:border-[#a9c8dc]/60 lg:pr-5">
                <div className="mt-1 h-9 w-9 shrink-0 rounded bg-blue-200/60" />
                <div className="min-w-0 flex-1 space-y-2">
                  <div className="h-5 w-32 rounded bg-blue-200/70" />
                  <div className="h-4 w-44 rounded bg-slate-300/70" />
                  <div className="h-3.5 w-36 rounded bg-slate-200/80" />
                </div>
              </div>

              {/* Metadata Column */}
              <div className="space-y-2 text-[12px] lg:border-r lg:border-[#a9c8dc]/60 lg:pr-5">
                <div className="flex items-center gap-2">
                  <div className="h-3 w-28 rounded bg-slate-200/80" />
                  <div className="h-3 w-36 rounded bg-slate-300/70" />
                </div>
                <div className="flex items-center gap-2">
                  <div className="h-3 w-32 rounded bg-slate-200/80" />
                  <div className="h-3 w-24 rounded bg-slate-300/70" />
                </div>
                <div className="flex items-center gap-2">
                  <div className="h-3 w-20 rounded bg-slate-200/80" />
                  <div className="h-3 w-16 rounded bg-slate-300/70" />
                </div>
                <div className="flex items-center gap-2">
                  <div className="h-3 w-24 rounded bg-slate-200/80" />
                  <div className="h-3 w-32 rounded bg-slate-300/70" />
                </div>
              </div>

              {/* Quick Links Column */}
              <div className="space-y-2.5">
                <div className="h-3.5 w-full rounded bg-slate-200/70" />
                <div className="h-3.5 w-4/5 rounded bg-slate-200/70" />
                <div className="h-3.5 w-full rounded bg-slate-200/70" />
                <div className="h-3.5 w-3/4 rounded bg-slate-200/70" />
              </div>
            </div>

            {/* Bottom Button Skeleton */}
            <div className="absolute bottom-0 right-5 translate-y-1/2 h-7 w-28 rounded-md bg-[#e7f3ff] border border-[#4485bb]/40" />
          </div>

          {/* Right Progress Card Skeleton */}
          <div className="overflow-hidden rounded-md border border-[#9fbfd5]/60 bg-[#e6f1f8] text-[12px] shadow-sm">
            <div className="grid grid-cols-[minmax(0,1fr)_74px] border-b border-[#9fbfd5]/60 bg-[#dcebf7] py-1.5 pl-[19px] pr-4">
              <div className="h-3.5 w-16 rounded bg-blue-200/70" />
              <div className="h-3.5 w-12 rounded bg-blue-200/70" />
            </div>
            <div className="space-y-2.5 p-3 pl-[17px]">
              {[1, 2, 3, 4, 5].map((i) => (
                <div key={i} className="flex items-center justify-between gap-2">
                  <div className="flex items-center gap-2">
                    <div className="h-3 w-3 rounded-full bg-blue-200/80" />
                    <div className="h-3 w-24 rounded bg-slate-200/80" />
                  </div>
                  <div className="h-3 w-16 rounded bg-slate-200/70" />
                </div>
              ))}
            </div>
          </div>
        </div>
      ) : (
        <div className="grid gap-3.5 lg:grid-cols-[minmax(0,1fr)_260px]">
          <div className="relative rounded-md border border-[#4387bd] bg-[#f5fbff] px-5 py-6 pb-9 shadow-sm lg:pb-6">
            <div className="grid gap-5 lg:grid-cols-[minmax(230px,1.1fr)_minmax(310px,1.5fr)_minmax(210px,0.9fr)] lg:items-center">
              <div className="flex min-w-0 items-start gap-4 lg:border-r lg:border-[#a9c8dc] lg:pr-5">
                <FileText className="mt-1 h-9 w-9 shrink-0 text-[#243685]" strokeWidth={1.4} />
                <div className="min-w-0 text-[#1d2e68]">
                  <h2 className="text-[20px] font-bold leading-6 tracking-tight">{details.code}</h2>
                  <p className="text-[15px] font-semibold leading-5">{details.title}</p>
                  <p className="mt-0.5 text-[12px] leading-[18px] text-[#34476f]">{details.office}</p>
                </div>
              </div>

              <dl className="grid min-w-0 grid-cols-[max-content_minmax(0,1fr)] gap-x-4 gap-y-1 text-[12px] leading-[18px] text-[#293d6b] lg:border-r lg:border-[#a9c8dc] lg:pr-5">
                <dt>Engagement Source:</dt>
                <dd>{details.originType === "Planned" ? "Internal Audit Planning (IAP)" : "Authorized Unplanned Engagement"}</dd>
                <dt>Authority Reference No:</dt>
                <dd className="break-words">{details.authorityReference || details.annualPlanRef || "—"}</dd>
                <dt>Audit Year:</dt>
                <dd>{auditYear}</dd>
                <dt>Period Covered:</dt>
                <dd>Jan 1, {periodYear} - Dec 31, {periodYear}</dd>
              </dl>

              <div className="space-y-1 text-[12px] leading-[18px] text-[#293d6b]">
                {[
                  "Engagement Source & Authorization",
                  "Engagement Identity",
                  "Initial Engagement Direction",
                  "Audit Team & Office Order",
                ].map((label) => (
                  <button
                    key={label}
                    type="button"
                    onClick={() => navigateTo(`${basePath}/details`)}
                    className="flex w-full cursor-pointer items-center justify-between gap-2 rounded px-1 text-left transition-colors duration-150 hover:bg-[#e4f1ff] hover:text-[#0c559d] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2478bf]"
                  >
                    <span>{label}</span>
                    <ChevronRight size={14} className="shrink-0 text-[#6da0c7]" />
                  </button>
                ))}
              </div>
            </div>
            <button
              type="button"
              onClick={() => navigateTo(`${basePath}/details`)}
              className="absolute bottom-0 right-5 translate-y-1/2 cursor-pointer rounded-md border border-[#4485bb] bg-[#e7f3ff] px-7 py-2 text-xs font-medium text-[#173575] shadow-sm transition-all duration-150 hover:border-[#1769b2] hover:bg-[#d5eaff] hover:text-[#0b4d91] hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2478bf]"
            >
              View Full Details
            </button>
          </div>

          <div className="overflow-hidden rounded-md border border-[#9fbfd5] bg-[#e6f1f8] text-[12px] text-[#293d6b] shadow-sm">
            <div className="grid grid-cols-[minmax(0,1fr)_74px] border-b border-[#9fbfd5] bg-[#dcebf7] py-0.5 pl-[19px] pr-4 text-[13px] font-bold leading-5 text-[#1d2e68]">
              <span>Progress</span><span>Status</span>
            </div>
            <div className="space-y-0 pb-[5px] pl-[17px] pr-2.5 pt-0 text-[12px] leading-[19px]">
              {progress.map(({ label, status, icon: Icon }) => (
                <div key={label} className="grid grid-cols-[minmax(0,1fr)_80px] items-center gap-1">
                  <span className="flex min-w-0 items-center gap-2 whitespace-nowrap"><Icon size={13} className="shrink-0 text-[#27388c]" />{label}</span>
                  <span className={status === "Done" ? "whitespace-nowrap text-[#568c67]" : status === "In Progress" ? "whitespace-nowrap text-[#c4873d]" : "whitespace-nowrap text-[#8998a4]"}>[ {status} ]</span>
                </div>
              ))}
            </div>
          </div>
        </div>
      )}

      <div>
        <div className="flex flex-wrap items-end justify-between gap-2">
          <div className="flex max-w-full flex-wrap border border-b-0 border-[#b8d1e4] bg-white">
            {workspaces.map(({ id, label, icon: Icon }) => (
              <button
                key={id}
                type="button"
                onClick={() => {
                  setSelectedWorkspace(id);
                  navigateTo(`${basePath}/${id}`);
                }}
                aria-pressed={selectedWorkspace === id}
                className={selectedWorkspace === id
                  ? "relative flex cursor-pointer items-center gap-2 border border-b-0 border-[#4485bb] bg-[#e7f3ff] px-5 py-2 text-sm font-semibold text-[#243685] transition-colors duration-150 hover:bg-[#d7ebff] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2478bf] after:absolute after:bottom-0 after:left-3 after:right-3 after:h-1 after:bg-[#00cfa5]"
                  : "flex cursor-pointer items-center gap-2 px-5 py-2 text-sm font-semibold text-[#243685] transition-colors duration-150 hover:bg-[#d7ebff] hover:text-[#0b4d91] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2478bf]"}
              >
                <Icon size={18} />{label}
              </button>
            ))}
          </div>
          <button
            type="button"
            onClick={() => navigateTo(`${basePath}/activity`)}
            className="mb-2 cursor-pointer rounded-md border border-[#4485bb] bg-[#e7f3ff] px-8 py-2 text-xs font-medium text-[#173575] transition-all duration-150 hover:border-[#1769b2] hover:bg-[#d5eaff] hover:text-[#0b4d91] hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2478bf]"
          >
            Activity Log
          </button>
        </div>

        <div className="border border-[#4485bb] bg-[#e7f3ff] p-4 sm:p-5">
          {selectedWorkspace === "planning" ? (
            <PlanningActivities details={details} engagementId={engagementId} />
          ) : selectedStage ? (
            <div className="flex min-h-[420px] flex-col items-center justify-center gap-4 rounded-md border border-[#9fc2db] bg-[#f4faff] text-center text-[#1d2e68]" aria-live="polite">
              <SelectedIcon size={64} className="text-[#27388c]" />
              <div>
                <h2 className="text-lg font-semibold">{selectedStage.label}</h2>
                <p className="mt-1 text-sm">Not Started Yet</p>
              </div>
            </div>
          ) : (
            <div className="flex min-h-[420px] items-center justify-center rounded-md border border-[#a9c8dc] bg-[#f8fcff] px-5 py-10">
              <div className="grid w-full max-w-[1040px] gap-6 sm:grid-cols-2 xl:grid-cols-4 xl:gap-10">
                {workspaces.map(({ id, label, icon: Icon }) => (
                  <div key={id} className="flex min-h-[276px] flex-col items-center justify-between rounded-xl border border-[#4485bb] bg-[#dfecfb] px-4 py-7 text-center text-[#26388c] shadow-sm">
                    <div className="flex flex-1 flex-col items-center justify-center gap-4">
                      <Icon size={96} />
                      <h3 className="max-w-[160px] text-lg font-semibold leading-tight">{label}</h3>
                    </div>
                    <button
                      type="button"
                      onClick={() => {
                        setSelectedWorkspace(id);
                        navigateTo(`${basePath}/${id}`);
                      }}
                      className="mt-5 inline-flex w-full max-w-[170px] cursor-pointer items-center justify-center gap-4 rounded-md border border-[#a4acb8] bg-white px-3 py-2 text-xs text-slate-700 shadow-sm transition-all duration-150 hover:border-[#2478bf] hover:bg-[#d8ecff] hover:text-[#0b4d91] hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2478bf]"
                    >
                      Open Activity <ArrowRight size={16} />
                    </button>
                  </div>
                ))}
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
