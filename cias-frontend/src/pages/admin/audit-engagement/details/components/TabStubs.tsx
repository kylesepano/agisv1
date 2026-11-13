import {
  Activity,
  Calendar,
  CheckCircle2,
  FileCheck2,
  FileSpreadsheet,
  FileText,
  Layers,
  Scale,
  ShieldCheck,
} from "lucide-react";
import type { FullEngagementDetails } from "../engagement-details-types";
import { Button } from "../../../../../components/ui/button";
import { navigateTo } from "../../../../../routes";

type TabStubProps = {
  details: FullEngagementDetails;
};

export function PlanningTabStub({ details }: TabStubProps) {
  return (
    <div className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm text-center space-y-4">
      <div className="mx-auto grid h-12 w-12 place-items-center rounded-xl bg-blue-50 text-blue-600 border border-blue-100">
        <FileSpreadsheet className="h-6 w-6" />
      </div>

      <div className="max-w-md mx-auto space-y-1">
        <h3 className="text-base font-bold text-slate-900">
          Planning Workspace & Audit Engagement Plan (AEP)
        </h3>
        <p className="text-xs text-slate-500 leading-relaxed">
          Upcoming in <strong>SCR-221 (Prepare Audit Engagement Plan)</strong>. Governs Process Flow Documentation, Risk Matrix, Audit Procedures, and Sampling Working Papers.
        </p>
      </div>

      {details.aeo && (
        <div className="inline-flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs text-emerald-900 font-medium">
          <CheckCircle2 size={13} className="text-emerald-600" />
          <span>Issued AEO Reference: <strong className="font-mono">{details.aeo.aeoNumber}</strong> ({details.aeo.version})</span>
        </div>
      )}

      <div className="pt-2 flex justify-center">
        <Button
          className="h-9 px-4 text-xs font-semibold bg-blue-600 hover:bg-blue-700 text-white flex items-center gap-1.5 cursor-pointer"
          onClick={() => navigateTo(`/admin/audit-engagement/${details.code}/prepare-order`)}
        >
          <FileText size={13} />
          <span>View Issued AEO Document (SCR-214)</span>
        </Button>
      </div>
    </div>
  );
}

export function ExecutionTabStub({ details }: TabStubProps) {
  return (
    <div className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm text-center space-y-3">
      <div className="mx-auto grid h-12 w-12 place-items-center rounded-xl bg-indigo-50 text-indigo-600 border border-indigo-100">
        <FileCheck2 className="h-6 w-6" />
      </div>
      <div className="max-w-md mx-auto space-y-1">
        <h3 className="text-base font-bold text-slate-900">
          Audit Execution & Working Papers Workspace
        </h3>
        <p className="text-xs text-slate-500 leading-relaxed">
          Upcoming in <strong>Phase 4 Execution (SCR-223)</strong>. Governs Fieldwork Records, Evidence Management, Sufficiency/Integrity Assessments, and Review Notes for <strong>{details.code}</strong>.
        </p>
      </div>
    </div>
  );
}

export function AuditIssuesTabStub({ details }: TabStubProps) {
  return (
    <div className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm text-center space-y-3">
      <div className="mx-auto grid h-12 w-12 place-items-center rounded-xl bg-amber-50 text-amber-600 border border-amber-100">
        <Scale className="h-6 w-6" />
      </div>
      <div className="max-w-md mx-auto space-y-1">
        <h3 className="text-base font-bold text-slate-900">
          Audit Issues Evaluation Workspace (SCR-230 Anchor)
        </h3>
        <p className="text-xs text-slate-500 leading-relaxed">
          Upcoming in <strong>SCR-230</strong>. Tracks preliminary matters under evaluation, supporting evidence trails, and conversion to Audit Finding Recommendations (AFRs) for <strong>{details.code}</strong>.
        </p>
      </div>
    </div>
  );
}

export function AfrsTabStub({ details }: TabStubProps) {
  return (
    <div className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm text-center space-y-3">
      <div className="mx-auto grid h-12 w-12 place-items-center rounded-xl bg-purple-50 text-purple-600 border border-purple-100">
        <FileText className="h-6 w-6" />
      </div>
      <div className="max-w-md mx-auto space-y-1">
        <h3 className="text-base font-bold text-slate-900">
          Audit Finding Recommendations (AFRs) (SCR-240 Anchor)
        </h3>
        <p className="text-xs text-slate-500 leading-relaxed">
          Upcoming in <strong>SCR-240</strong>. Manages Criteria, Condition, Cause, Consequence/Risk (5Cs), auditee comments, and auditor rejoinders for <strong>{details.code}</strong>.
        </p>
      </div>
    </div>
  );
}

export function ConferencesTabStub({ details }: TabStubProps) {
  return (
    <div className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm text-center space-y-3">
      <div className="mx-auto grid h-12 w-12 place-items-center rounded-xl bg-emerald-50 text-emerald-600 border border-emerald-100">
        <Calendar className="h-6 w-6" />
      </div>
      <div className="max-w-md mx-auto space-y-1">
        <h3 className="text-base font-bold text-slate-900">
          Conferences & Stakeholder Engagement
        </h3>
        <p className="text-xs text-slate-500 leading-relaxed">
          Upcoming in <strong>Conferences Workspace</strong>. Manages Entry & Exit Conference notices, agendas, minutes, attendee rosters, and signed agreements for <strong>{details.code}</strong>.
        </p>
      </div>
    </div>
  );
}

export function AuditReportsTabStub({ details }: TabStubProps) {
  return (
    <div className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm text-center space-y-3">
      <div className="mx-auto grid h-12 w-12 place-items-center rounded-xl bg-blue-50 text-blue-600 border border-blue-100">
        <ShieldCheck className="h-6 w-6" />
      </div>
      <div className="max-w-md mx-auto space-y-1">
        <h3 className="text-base font-bold text-slate-900">
          Audit Reporting & Issuance (SCR-250 Anchor)
        </h3>
        <p className="text-xs text-slate-500 leading-relaxed">
          Upcoming in <strong>SCR-250</strong>. Governs Interim, Draft, and Final Audit Reports with Department Head review and controlled distribution for <strong>{details.code}</strong>.
        </p>
      </div>
    </div>
  );
}

export function CompletionTransferTabStub({ details }: TabStubProps) {
  return (
    <div className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm text-center space-y-3">
      <div className="mx-auto grid h-12 w-12 place-items-center rounded-xl bg-slate-100 text-slate-700 border border-slate-200">
        <Layers className="h-6 w-6" />
      </div>
      <div className="max-w-md mx-auto space-y-1">
        <h3 className="text-base font-bold text-slate-900">
          Completion, CMS Transfer & Administrative Closure
        </h3>
        <p className="text-xs text-slate-500 leading-relaxed">
          Upcoming in <strong>Phase 9-10 (SCR-260)</strong>. Handles CMS recommendation transfer, reconciliation, and locking of engagement records for <strong>{details.code}</strong>.
        </p>
      </div>
    </div>
  );
}

export function ActivityTabStub({ details, isLoading }: TabStubProps & { isLoading?: boolean }) {
  return (
    <div className="overflow-hidden rounded-md border border-[#9fc2db] bg-white font-sans text-[#1d2e68]">
      {/* Table-style Header Bar matching PlanningActivities */}
      <div className="flex items-center justify-between gap-2.5 border-b border-[#9fc2db] bg-[#dcebf7] px-4 py-2.5 text-[14px] font-bold leading-5 text-[#1d2e68]">
        <div className="flex items-center gap-2.5">
          <div className="flex h-5 w-5 items-center justify-center rounded border border-[#2b4c7e] bg-white text-[#2b4c7e]">
            <Activity size={13} strokeWidth={2} />
          </div>
          <h2>Activity Log</h2>
        </div>
        {isLoading ? (
          <span className="h-4 w-24 animate-pulse rounded bg-blue-200/70" />
        ) : (
          <span className="text-[12px] font-medium text-[#293d6b]">
            {details.recentActivity.length}{" "}
            {details.recentActivity.length === 1 ? "Event Logged" : "Events Logged"}
          </span>
        )}
      </div>

      {isLoading ? (
        <div className="min-h-[420px] divide-y divide-[#dcebf7] bg-white">
          {[1, 2, 3, 4].map((idx) => (
            <div key={idx} className="px-5 py-4 animate-pulse space-y-2">
              <div className="flex items-center justify-between gap-2">
                <div className="h-3.5 w-44 rounded bg-blue-200/70" />
                <div className="h-3 w-28 rounded bg-slate-200" />
              </div>
              <div className="h-3 w-3/4 rounded bg-slate-200/80" />
              <div className="flex items-center justify-between gap-2 pt-1">
                <div className="h-3 w-40 rounded bg-slate-100" />
                <div className="h-4 w-28 rounded bg-[#e7f3ff]" />
              </div>
            </div>
          ))}
        </div>
      ) : details.recentActivity.length === 0 ? (
        <div className="flex min-h-[420px] flex-col items-center justify-center bg-white px-6 py-12 text-center">
          <div className="flex h-[76px] w-[76px] items-center justify-center rounded-2xl border-2 border-[#5464a0] bg-[#f4f7fc] text-[#5464a0] shadow-2xs">
            <Activity className="h-10 w-10" strokeWidth={1.8} />
          </div>
          <h3 className="mt-6 text-[18px] font-bold text-[#1d2e68]">
            Audit &amp; System Activity History
          </h3>
          <p className="mt-2.5 max-w-[440px] text-[13px] leading-relaxed text-slate-600">
            No activity events have been logged for this engagement yet.
          </p>
          <p className="mt-3.5 max-w-[420px] text-[12px] leading-relaxed text-slate-500">
            Actions performed across planning, workflow transitions, and updates will appear here.
          </p>
        </div>
      ) : (
        <div className="min-h-[420px] divide-y divide-[#dcebf7] bg-white">
          {details.recentActivity.map((evt) => (
            <div
              key={evt.id}
              className="px-5 py-3.5 transition-colors duration-150 hover:bg-[#f4faff]"
            >
              <div className="flex flex-wrap items-center justify-between gap-2">
                <h3 className="text-[13px] font-bold leading-5 text-[#1d2e68]">
                  {evt.title}
                </h3>
                <span className="text-[12px] font-medium text-slate-500">
                  {evt.timestamp}
                </span>
              </div>
              <p className="mt-1 text-[13px] leading-relaxed text-slate-600">
                {evt.description}
              </p>
              <div className="mt-2 flex flex-wrap items-center justify-between gap-2 text-[12px] text-slate-500">
                <span>
                  Actor:{" "}
                  <strong className="font-semibold text-[#1d2e68]">
                    {evt.actor}
                  </strong>{" "}
                  ({evt.role})
                </span>
                <span className="rounded border border-[#7bb3e2] bg-[#e7f3ff] px-2.5 py-0.5 text-[11px] font-semibold text-[#1d2e68]">
                  {evt.phase}
                </span>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
