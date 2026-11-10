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

export function ActivityTabStub({ details }: TabStubProps) {
  return (
    <div className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm space-y-3">
      <div className="flex items-center justify-between border-b border-slate-100 pb-2">
        <div className="flex items-center gap-1.5 font-bold text-slate-800 text-xs">
          <Activity size={14} className="text-blue-600" />
          <span>Full Audit & System Activity History (§10.9)</span>
        </div>
        <span className="text-[10px] font-mono text-slate-400">
          Total {details.recentActivity.length} Events Logged
        </span>
      </div>

      <div className="divide-y divide-slate-100">
        {details.recentActivity.map((evt) => (
          <div key={evt.id} className="py-2 text-xs space-y-0.5">
            <div className="flex items-center justify-between">
              <span className="font-bold text-slate-900">{evt.title}</span>
              <span className="font-mono text-[10px] text-slate-400">{evt.timestamp}</span>
            </div>
            <p className="text-[11px] text-slate-600">{evt.description}</p>
            <div className="flex items-center justify-between text-[10px] text-slate-400">
              <span>Actor: <strong className="text-slate-700">{evt.actor}</strong> ({evt.role})</span>
              <span className="font-semibold text-indigo-700 bg-indigo-50 px-1.5 py-0.2 rounded border border-indigo-100">
                {evt.phase}
              </span>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
