import {
  Activity,
  Calendar,
  Clock,
  FileCheck,
  FileText,
  Layers,
  Scale,
  Shield,
  Users,
} from "lucide-react";
import type { FullEngagementDetails } from "../engagement-details-types";
import { navigateTo } from "../../../../../routes";

type OverviewTabContentProps = {
  details: FullEngagementDetails;
};

export function OverviewTabContent({ details }: OverviewTabContentProps) {
  return (
    <div className="space-y-3">
      {/* Top 4-Metric Operational Stat Strip */}
      <div className="grid grid-cols-2 gap-2.5 sm:grid-cols-4">
        <div className="rounded-lg border border-slate-200 bg-white p-3 shadow-sm space-y-1">
          <div className="flex items-center justify-between text-[11px] font-semibold text-slate-500">
            <span>Working Papers</span>
            <FileText size={13} className="text-blue-600" />
          </div>
          <p className="text-xl font-bold text-slate-900 font-mono">
            {details.counts.workingPapersCount}
          </p>
          <span className="text-[10px] text-slate-400">Fieldwork WPs</span>
        </div>

        <div className="rounded-lg border border-slate-200 bg-white p-3 shadow-sm space-y-1">
          <div className="flex items-center justify-between text-[11px] font-semibold text-slate-500">
            <span>Evidence Items</span>
            <FileCheck size={13} className="text-emerald-600" />
          </div>
          <p className="text-xl font-bold text-slate-900 font-mono">
            {details.counts.evidenceItemsCount}
          </p>
          <span className="text-[10px] text-emerald-600 font-semibold">Registered</span>
        </div>

        <div className="rounded-lg border border-slate-200 bg-white p-3 shadow-sm space-y-1">
          <div className="flex items-center justify-between text-[11px] font-semibold text-slate-500">
            <span>Audit Issues</span>
            <Scale size={13} className="text-amber-600" />
          </div>
          <p className="text-xl font-bold text-slate-900 font-mono">
            {details.counts.auditIssuesCount}
          </p>
          <span className="text-[10px] text-amber-600 font-semibold">Under Evaluation</span>
        </div>

        <div className="rounded-lg border border-slate-200 bg-white p-3 shadow-sm space-y-1">
          <div className="flex items-center justify-between text-[11px] font-semibold text-slate-500">
            <span>Planned Man-Days</span>
            <Clock size={13} className="text-indigo-600" />
          </div>
          <p className="text-xl font-bold text-slate-900 font-mono">
            {details.plannedManDays}d
          </p>
          <span className="text-[10px] text-indigo-600 font-semibold">Resource Window</span>
        </div>
      </div>

      {/* Main 2-Column Split */}
      <div className="grid grid-cols-1 gap-3 xl:grid-cols-12">
        {/* Left Column (8 cols): Mandate & Scope Details */}
        <div className="space-y-3 xl:col-span-8">
          {/* Authority & Legal Mandate Card (§5.4) */}
          <div className="rounded-lg border border-slate-200 bg-white p-3.5 shadow-sm space-y-2.5">
            <div className="flex items-center justify-between border-b border-slate-100 pb-2">
              <div className="flex items-center gap-1.5">
                <Shield className="h-4 w-4 text-blue-600" />
                <h3 className="text-[13px] font-bold text-slate-800">
                  Authority & Engagement Mandate (§5.4)
                </h3>
              </div>
              <span className="text-[10px] text-slate-400 font-mono">
                Date Authorized: {details.dateAuthorized}
              </span>
            </div>

            <div className="grid grid-cols-1 gap-2.5 sm:grid-cols-2 text-xs">
              <div className="rounded-md border border-slate-100 bg-slate-50/70 p-2.5 space-y-0.5">
                <span className="text-[10px] font-semibold text-slate-400 uppercase tracking-wider block">
                  Authority Reference
                </span>
                <p className="font-semibold text-slate-900 leading-snug">
                  {details.authorityReference}
                </p>
              </div>

              <div className="rounded-md border border-slate-100 bg-slate-50/70 p-2.5 space-y-0.5">
                <span className="text-[10px] font-semibold text-slate-400 uppercase tracking-wider block">
                  Directing / Requesting Authority
                </span>
                <p className="font-semibold text-slate-900 leading-snug">
                  {details.directingAuthority}
                </p>
              </div>
            </div>
          </div>

          {/* Objectives, Areas & Scope Boundaries Card */}
          <div className="rounded-lg border border-slate-200 bg-white p-3.5 shadow-sm space-y-3">
            <div className="flex items-center justify-between border-b border-slate-100 pb-2">
              <div className="flex items-center gap-1.5">
                <Layers className="h-4 w-4 text-blue-600" />
                <h3 className="text-[13px] font-bold text-slate-800">
                  Objectives, Scope & Audit Focuses
                </h3>
              </div>
              <span className="text-[10px] text-slate-400 font-medium">
                SCR-212 Controlled Baseline
              </span>
            </div>

            {/* Objectives */}
            <div className="space-y-1">
              <span className="text-xs font-bold text-slate-700 block">
                Formal Audit Objectives
              </span>
              <p className="rounded-lg border border-slate-100 bg-slate-50/50 p-2.5 text-xs text-slate-700 leading-relaxed">
                {details.objectives}
              </p>
            </div>

            {/* Audit Areas & Focuses */}
            <div className="space-y-2">
              <span className="text-xs font-bold text-slate-700 block">
                In-Scope Audit Areas & Focus Sub-Areas
              </span>
              <div className="space-y-1.5">
                {details.auditAreas.map((area, idx) => (
                  <div
                    key={idx}
                    className="rounded-lg border border-blue-100 bg-blue-50/40 p-2.5 space-y-1"
                  >
                    <div className="flex items-center gap-1.5 text-xs font-bold text-blue-950">
                      <span className="font-mono text-[10px] bg-white px-1.5 py-0.2 rounded border border-blue-200 text-blue-700">
                        Area {idx + 1}
                      </span>
                      <span>{area}</span>
                    </div>
                  </div>
                ))}
              </div>

              {/* Tagged Focuses */}
              <div className="flex flex-wrap items-center gap-1.5 pt-1">
                <span className="text-[10px] font-semibold text-slate-400">Focuses:</span>
                {details.auditFocuses.map((focus, idx) => (
                  <span
                    key={idx}
                    className="rounded bg-slate-100 px-2 py-0.5 text-[10px] font-medium text-slate-700 border border-slate-200"
                  >
                    {focus}
                  </span>
                ))}
              </div>
            </div>

            {/* Boundaries & Limitations 3-Col Box */}
            <div className="grid grid-cols-1 gap-2 sm:grid-cols-3 text-xs pt-1 border-t border-slate-100">
              <div className="rounded bg-slate-50/70 p-2 border border-slate-100 space-y-0.5">
                <span className="text-[10px] font-bold text-slate-500 block">Boundaries</span>
                <p className="text-[11px] text-slate-600 leading-snug">{details.boundaries}</p>
              </div>
              <div className="rounded bg-slate-50/70 p-2 border border-slate-100 space-y-0.5">
                <span className="text-[10px] font-bold text-slate-500 block">Exclusions</span>
                <p className="text-[11px] text-slate-600 leading-snug">{details.exclusions}</p>
              </div>
              <div className="rounded bg-slate-50/70 p-2 border border-slate-100 space-y-0.5">
                <span className="text-[10px] font-bold text-slate-500 block">Limitations</span>
                <p className="text-[11px] text-slate-600 leading-snug">{details.knownLimitations}</p>
              </div>
            </div>
          </div>
        </div>

        {/* Right Column (4 cols): Team, Issued AEO & Timeline */}
        <div className="space-y-3 xl:col-span-4">
          {/* Assigned Audit Team Card */}
          <div className="rounded-lg border border-slate-200 bg-white p-3.5 shadow-sm space-y-2.5">
            <div className="flex items-center justify-between border-b border-slate-100 pb-2">
              <div className="flex items-center gap-1.5">
                <Users className="h-4 w-4 text-indigo-600" />
                <h3 className="text-[13px] font-bold text-slate-800">
                  Assigned Audit Team
                </h3>
              </div>
              <span className="text-[10px] font-bold text-indigo-700 bg-indigo-50 px-1.5 py-0.2 rounded border border-indigo-200">
                {details.team.length} Members
              </span>
            </div>

            <div className="space-y-1.5">
              {details.team.map((m) => (
                <div
                  key={m.id}
                  className="flex items-center justify-between p-2 rounded-md bg-slate-50/70 border border-slate-100 text-xs"
                >
                  <div className="min-w-0">
                    <p className="font-bold text-slate-900 truncate">{m.name}</p>
                    <p className="text-[10px] text-slate-500 truncate">{m.email}</p>
                  </div>
                  <span
                    className={`rounded px-1.5 py-0.5 text-[9px] font-bold border shrink-0 ${
                      m.role === "Lead Auditor"
                        ? "bg-blue-50 text-blue-700 border-blue-200"
                        : "bg-white text-slate-600 border-slate-200"
                    }`}
                  >
                    {m.role}
                  </span>
                </div>
              ))}
            </div>

            {/* Execution Schedule Window */}
            <div className="pt-2 border-t border-slate-100 space-y-1 text-xs">
              <div className="flex items-center justify-between text-[11px] text-slate-600">
                <span className="flex items-center gap-1">
                  <Calendar size={12} className="text-slate-400" />
                  Period:
                </span>
                <span className="font-mono font-bold text-slate-800">
                  {details.startDate} ~ {details.endDate}
                </span>
              </div>
              <div>
                <div className="flex items-center justify-between text-[10px] text-slate-500 mb-0.5">
                  <span>Lifecycle Progress</span>
                  <span className="font-bold text-blue-700">{details.progressPercent}%</span>
                </div>
                <div className="h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
                  <div
                    className="h-full bg-blue-600 rounded-full transition-all"
                    style={{ width: `${details.progressPercent}%` }}
                  />
                </div>
              </div>
            </div>
          </div>

          {/* Issued AEO Reference Card */}
          {details.aeo && (
            <div className="rounded-lg border border-emerald-200 bg-emerald-50/40 p-3 space-y-2 text-xs">
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-1.5 font-bold text-emerald-950">
                  <FileText className="h-4 w-4 text-emerald-600" />
                  <span>Issued AEO Reference</span>
                </div>
                <span className="rounded bg-emerald-100 px-1.5 py-0.2 text-[9px] font-bold text-emerald-800">
                  {details.aeo.version}
                </span>
              </div>
              <div className="space-y-1 text-[11px] text-slate-600">
                <p>
                  Order No: <strong className="font-mono text-emerald-900">{details.aeo.aeoNumber}</strong>
                </p>
                <p>Effective: <span className="font-mono font-medium">{details.aeo.effectiveDate}</span></p>
                <p>Approved By: <span className="font-medium text-slate-800">{details.aeo.approver}</span></p>
              </div>
              <button
                type="button"
                onClick={() => navigateTo(`/admin/audit-engagement/${details.code}/prepare-order`)}
                className="text-[11px] font-bold text-blue-700 hover:text-blue-800 underline block pt-0.5 cursor-pointer"
              >
                View Full Controlled AEO Document &rarr;
              </button>
            </div>
          )}

          {/* Recent Activity Timeline Feed */}
          <div className="rounded-lg border border-slate-200 bg-white p-3.5 shadow-sm space-y-2.5">
            <div className="flex items-center justify-between border-b border-slate-100 pb-2">
              <div className="flex items-center gap-1.5">
                <Activity className="h-4 w-4 text-blue-600" />
                <h3 className="text-[13px] font-bold text-slate-800">
                  Recent Foundation Activity
                </h3>
              </div>
              <span className="text-[10px] text-slate-400 font-mono">Audit Log</span>
            </div>

            <div className="space-y-2 text-xs">
              {details.recentActivity.map((act) => (
                <div
                  key={act.id}
                  className="rounded-md bg-slate-50/70 p-2 border border-slate-100 space-y-0.5"
                >
                  <div className="flex items-center justify-between">
                    <span className="font-bold text-slate-800">{act.title}</span>
                    <span className="font-mono text-[9px] text-slate-400">{act.timestamp}</span>
                  </div>
                  <p className="text-[11px] text-slate-600">{act.description}</p>
                  <div className="flex items-center justify-between pt-0.5 text-[10px] text-slate-500">
                    <span>{act.actor}</span>
                    <span className="font-semibold text-indigo-700">{act.phase}</span>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
